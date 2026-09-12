<?php

namespace App\Services\Suunto;

use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Menarik workout dari Suunto Cloud API lalu menuliskannya ke tabel RAGA yang
 * sudah ada (`workouts`, `workout_samples`, `workout_laps`) lewat importer yang
 * sama dengan Garmin — payload dipetakan ke bentuk yang dipahami importer dan
 * diberi label `source = suunto`, sehingga tidak ada logika penyimpanan ganda.
 *
 * Sinkron (tanpa queue worker), sama seperti GarminSyncService: pemanggilnya
 * tombol di Settings atau command artisan, dan hasilnya ingin langsung terlihat.
 *
 * Kuota: Suunto membatasi jumlah panggilan per minggu, jadi daftar workout
 * diambil sekali per rentang tanggal dengan `extensions` (stream HR/GPS ikut
 * dalam respons yang sama) — bukan satu panggilan per aktivitas.
 */
class SuuntoSyncService
{
    /** Cukup lama untuk sync+import+recovery; mencegah sync ganda per user. */
    private const LOCK_SECONDS = 300;

    public const DEFAULT_DAYS = 7;

    /** Stream yang dibutuhkan RAGA: HR (Relative Effort), GPS, kecepatan, elevasi, cadence. */
    private const STREAM_EXTENSIONS = [
        'HeartrateStreamExtension',
        'HeartRateExtension',
        'SpeedStreamExtension',
        'AltitudeStreamExtension',
        'CadenceStreamExtension',
        'LocationStreamExtension',
    ];

    public function __construct(private SuuntoWorkoutMapper $mapper) {}

    public static function streamExtensions(): string
    {
        return implode(',', self::STREAM_EXTENSIONS);
    }

    /**
     * @return array{status: 'success'|'error', days: int, imported: int, skipped: int, message: ?string, import_output: ?string}
     */
    public function syncForUser(User $user, int $days = self::DEFAULT_DAYS): array
    {
        $days = max(1, min($days, 90));

        $lock = Cache::lock('suunto-sync:user:'.$user->id, self::LOCK_SECONDS);

        if (! $lock->get()) {
            return $this->result('error', $days, 0, 0, 'Sinkronisasi Suunto sedang berjalan untuk akun ini. Coba lagi beberapa saat.');
        }

        try {
            return $this->doSync($user, $days);
        } finally {
            $lock->release();
        }
    }

    /**
     * @return array{status: 'success'|'error', days: int, imported: int, skipped: int, message: ?string, import_output: ?string}
     */
    private function doSync(User $user, int $days): array
    {
        $connection = $user->suuntoConnection;

        if (! $connection) {
            return $this->result('error', $days, 0, 0, 'Belum terhubung ke Suunto.');
        }

        if (! SuuntoApiClient::isConfigured()) {
            $message = 'Kredensial Suunto belum lengkap (SUUNTO_CLIENT_ID / SUUNTO_CLIENT_SECRET / SUUNTO_SUBSCRIPTION_KEY).';
            $connection->update(['last_synced_at' => now(), 'last_sync_status' => 'error', 'last_sync_message' => $message]);

            return $this->result('error', $days, 0, 0, $message);
        }

        try {
            $client = new SuuntoApiClient($connection);
            $payload = $client->workouts($this->listParams($days));

            $activities = [];
            $skipped = 0;

            foreach ($this->extractWorkouts($payload) as $workout) {
                if (! is_array($workout)) {
                    $skipped++;

                    continue;
                }

                try {
                    $activity = $this->mapper->toGarminActivity($workout);
                } catch (Throwable $e) {
                    $skipped++;
                    Log::warning('Suunto workout dilewati saat pemetaan.', ['error' => $e->getMessage()]);

                    continue;
                }

                if (empty($activity['startTimeLocal']) || empty($activity['duration'])) {
                    $skipped++;

                    continue;
                }

                $activities[] = $activity;
            }

            $importOutput = $activities === [] ? null : $this->import($user, $activities);

            Artisan::call('recovery:calculate', ['--days' => $days, '--user' => $user->id]);

            // Data baru sudah masuk — buang konteks AI yang di-cache agar pesan
            // berikutnya memakai angka terbaru.
            Cache::forget('ai-context:user:'.$user->id);

            $connection->update([
                'last_synced_at' => now(),
                'last_sync_status' => 'success',
                'last_sync_message' => $activities === []
                    ? 'Tidak ada workout baru pada rentang ini.'
                    : null,
            ]);

            return $this->result('success', $days, count($activities), $skipped, null, $importOutput);
        } catch (SuuntoApiException $e) {
            $connection->update(['last_synced_at' => now(), 'last_sync_status' => 'error', 'last_sync_message' => $e->getMessage()]);

            return $this->result('error', $days, 0, 0, $e->getMessage());
        } catch (Throwable $e) {
            Log::error('Suunto sync gagal.', ['user_id' => $user->id, 'error' => $e->getMessage()]);
            $connection->update(['last_synced_at' => now(), 'last_sync_status' => 'error', 'last_sync_message' => 'Sinkronisasi gagal: '.$e->getMessage()]);

            return $this->result('error', $days, 0, 0, 'Sinkronisasi gagal: '.$e->getMessage());
        }
    }

    /**
     * Parameter daftar workout Suunto (`/v3/workouts`).
     *
     * @return array<string, string|int>
     */
    private function listParams(int $days): array
    {
        return [
            'from' => now()->subDays($days)->toDateString(),
            'to' => now()->toDateString(),
            'limit' => (int) config('services.suunto.sync_limit', 100),
            'extensions' => self::streamExtensions(),
        ];
    }

    /**
     * Ambil daftar workout dari respons apa pun bentuk bungkusnya.
     *
     * @param  array<string, mixed>  $payload
     * @return list<mixed>
     */
    private function extractWorkouts(array $payload): array
    {
        if (array_is_list($payload)) {
            return $payload;
        }

        foreach (['workouts', 'payload', 'data', 'items'] as $key) {
            if (isset($payload[$key]) && is_array($payload[$key])) {
                return array_values($payload[$key]);
            }
        }

        return [];
    }

    /**
     * @param  list<array<string, mixed>>  $activities
     */
    private function import(User $user, array $activities): ?string
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'suunto_sync_');
        file_put_contents($tmpFile, json_encode(['activities' => $activities], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        try {
            Artisan::call('garmin:import', [
                'path' => $tmpFile,
                '--user' => $user->id,
                '--source' => 'suunto',
            ]);

            $output = trim(Artisan::output());
        } finally {
            @unlink($tmpFile);
        }

        return $output !== '' ? $output : null;
    }

    /**
     * @return array{status: 'success'|'error', days: int, imported: int, skipped: int, message: ?string, import_output: ?string}
     */
    private function result(string $status, int $days, int $imported, int $skipped, ?string $message, ?string $importOutput = null): array
    {
        return [
            'status' => $status,
            'days' => $days,
            'imported' => $imported,
            'skipped' => $skipped,
            'message' => $message,
            'import_output' => $importOutput,
        ];
    }
}
