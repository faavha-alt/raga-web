<?php

namespace App\Services\Suunto;

use App\Models\SleepSession;
use App\Models\SuuntoConnection;
use App\Models\User;
use App\Models\Workout;
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

    public function __construct(
        private SuuntoWorkoutMapper $mapper,
        private SuuntoToolMapper $toolMapper,
    ) {}

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

        // Dua jalur: `password` (CLI suuntool, backend aplikasi Suunto) atau
        // `oauth` (Suunto Cloud API resmi, butuh Partner Program).
        return $connection->auth_mode === SuuntoToolClient::authMode()
            ? $this->syncViaTool($user, $connection, $days)
            : $this->syncViaApi($user, $connection, $days);
    }

    /**
     * Jalur tidak resmi: CLI `suuntool` (backend aplikasi Suunto).
     *
     * @return array{status: 'success'|'error', days: int, imported: int, skipped: int, message: ?string, import_output: ?string}
     */
    private function syncViaTool(User $user, SuuntoConnection $connection, int $days): array
    {
        if (! SuuntoToolClient::isAvailable()) {
            $message = 'Binary `'.SuuntoToolClient::binary().'` tidak ditemukan di server. Pasang suuntool (lihat README).';
            $connection->update(['last_synced_at' => now(), 'last_sync_status' => 'error', 'last_sync_message' => $message]);

            return $this->result('error', $days, 0, 0, $message);
        }

        $client = new SuuntoToolClient($user);

        if (! $client->hasSession()) {
            $message = 'Sesi Suunto belum ada atau sudah kedaluwarsa. Masukkan ulang email & password Suunto.';
            $connection->update(['last_synced_at' => now(), 'last_sync_status' => 'error', 'last_sync_message' => $message]);

            return $this->result('error', $days, 0, 0, $message);
        }

        try {
            $since = now()->subDays($days)->toDateString();
            $workouts = $client->workoutsSince($since);

            $activities = [];
            $skipped = 0;

            foreach ($workouts as $workout) {
                if (! is_array($workout)) {
                    $skipped++;

                    continue;
                }

                // Peta dulu tanpa SML: cukup untuk tahu waktu mulai, sehingga
                // workout yang sudah ada tidak perlu mengunduh sampel ~5 MB.
                $summary = $this->toolMapper->toGarminActivity($workout);

                if (empty($summary['startTimeLocal']) || empty($summary['duration'])) {
                    $skipped++;

                    continue;
                }

                $alreadyImported = Workout::where('user_id', $user->id)
                    ->where('source', 'suunto')
                    ->where('start_date', $summary['startTimeLocal'])
                    ->exists();

                if ($alreadyImported) {
                    $skipped++;

                    continue;
                }

                $samples = $this->fetchSamples($client, $workout);

                $activities[] = $this->toolMapper->toGarminActivity($workout, $samples);
            }

            $importOutput = $activities === [] ? null : $this->import($user, $activities);
            $sleepCount = $this->importSleep($user, $client, $days);

            Artisan::call('recovery:calculate', ['--days' => $days, '--user' => $user->id]);
            Cache::forget('ai-context:user:'.$user->id);

            $connection->update([
                'last_synced_at' => now(),
                'last_sync_status' => 'success',
                'last_sync_message' => $activities === [] && $sleepCount === 0
                    ? 'Tidak ada data baru pada rentang ini.'
                    : null,
            ]);

            return $this->result('success', $days, count($activities), $skipped, null, $importOutput);
        } catch (SuuntoApiException $e) {
            $connection->update(['last_synced_at' => now(), 'last_sync_status' => 'error', 'last_sync_message' => $e->getMessage()]);

            return $this->result('error', $days, 0, 0, $e->getMessage());
        } catch (Throwable $e) {
            Log::error('Suunto (suuntool) sync gagal.', ['user_id' => $user->id, 'error' => $e->getMessage()]);
            $connection->update(['last_synced_at' => now(), 'last_sync_status' => 'error', 'last_sync_message' => 'Sinkronisasi gagal: '.$e->getMessage()]);

            return $this->result('error', $days, 0, 0, 'Sinkronisasi gagal: '.$e->getMessage());
        }
    }

    /**
     * Ambil sampel per-detik bila key workout tersedia; kegagalan satu workout
     * tidak menggagalkan sync (workout tetap masuk sebagai ringkasan).
     *
     * @param  array<string, mixed>  $workout
     * @return array<string, mixed>|null
     */
    private function fetchSamples(SuuntoToolClient $client, array $workout): ?array
    {
        $key = null;

        foreach (['key', 'workoutKey', 'id'] as $candidate) {
            if (isset($workout[$candidate]) && is_scalar($workout[$candidate])) {
                $key = (string) $workout[$candidate];
                break;
            }
        }

        if ($key === null) {
            return null;
        }

        try {
            return $client->workoutSamples($key);
        } catch (Throwable $e) {
            Log::warning('Sampel Suunto dilewati.', ['key' => $key, 'error' => $e->getMessage()]);

            return null;
        }
    }

    /** Impor tidur dari `wellness sleep`; jumlah baris yang ditulis. */
    private function importSleep(User $user, SuuntoToolClient $client, int $days): int
    {
        try {
            $entries = $client->sleepSince(now()->subDays($days)->toDateString());
        } catch (Throwable $e) {
            Log::warning('Wellness sleep Suunto dilewati.', ['user_id' => $user->id, 'error' => $e->getMessage()]);

            return 0;
        }

        $sessions = $this->toolMapper->sleepSessions($entries, (int) $user->id, 'suunto');
        $written = 0;

        foreach ($sessions as $attributes) {
            SleepSession::updateOrCreate(
                [
                    'user_id' => $attributes['user_id'],
                    'source' => $attributes['source'],
                    'bedtime' => $attributes['bedtime'],
                ],
                $attributes,
            );

            $written++;
        }

        return $written;
    }

    /** Jalur resmi: Suunto Cloud API (OAuth2 + subscription key). */
    private function syncViaApi(User $user, SuuntoConnection $connection, int $days): array
    {
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
