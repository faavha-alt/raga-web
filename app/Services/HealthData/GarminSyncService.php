<?php

namespace App\Services\HealthData;

use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

/**
 * Runs a Garmin Connect pull for a user (scripts/garmin_sync.py), imports the
 * result into RAGA, then recomputes recovery for the affected days. Shared by
 * the web Settings > Garmin "Sync" button and the raga_sync_garmin MCP tool so
 * both take exactly the same path and leave the same audit trail on the user's
 * GarminConnection row.
 *
 * Synchronous on purpose: there is no queue worker in production, and callers
 * (a form submit, an MCP tool call) want the outcome in the same request.
 */
class GarminSyncService
{
    private const TIMEOUT_SECONDS = 120;

    /** Lock TTL — safely longer than the sync+import+recovery so a concurrent
     *  call can't start while another sync for the same user is still running. */
    private const LOCK_SECONDS = 300;

    /** Backfill memproses ribuan hari, jadi timeout per chunk diturunkan dari
     *  ukuran chunk (bukan total) agar satu chunk raksasa tidak menahan request. */
    private const BACKFILL_SECONDS_PER_DAY = 30;

    public static function pythonBinary(): string
    {
        return config('services.garmin.python_binary', env('PYTHON_BINARY', '/usr/bin/python3'));
    }

    public static function tokenStorePathForUser(User $user): string
    {
        $userStore = storage_path('app/garmin_tokens/'.$user->id);

        if (! is_dir($userStore)) {
            $legacyStore = getenv('HOME') ? getenv('HOME').'/.garmin_tokens' : null;
            if ($legacyStore && is_dir($legacyStore)) {
                File::makeDirectory($userStore, 0700, true, true);
                File::copyDirectory($legacyStore, $userStore);
            }
        }

        return $userStore;
    }

    /**
     * @return array{status: 'success'|'error', days: int, message: ?string, import_output: ?string}
     */
    public function syncForUser(User $user, int $days = 2): array
    {
        $lock = Cache::lock('garmin-sync:user:'.$user->id, self::LOCK_SECONDS);

        if (! $lock->get()) {
            return [
                'status' => 'error',
                'days' => $days,
                'message' => 'Sinkronisasi Garmin sedang berjalan untuk akun ini. Coba lagi beberapa saat.',
                'import_output' => null,
            ];
        }

        try {
            return $this->doSync($user, $days);
        } finally {
            $lock->release();
        }
    }

    /**
     * Backfill data lama dengan memotong rentang menjadi chunk kecil, supaya
     * tidak ada satu proses python/import yang kehabisan timeout.
     *
     * Urutan: TERBARU DULU (offset 0, lalu chunk, 2*chunk, ...). Data paling
     * relevan masuk lebih dulu; bila chunk belakangan gagal, progres chunk
     * sebelumnya tidak dihapus. `syncForUser()` tidak berubah.
     *
     * @param  callable(array{chunk: int, chunks: int, days: int, offset: int}): void|null  $progress
     * @return array{status: 'success'|'error', days: int, chunks: int, import_output: ?string, message: ?string}
     */
    public function backfill(User $user, int $days, int $chunkDays = 60, ?callable $progress = null): array
    {
        $days = max(1, $days);
        $chunkDays = max(1, $chunkDays);

        // Lock menutupi seluruh backfill — jauh lebih lama dari sync biasa.
        $totalChunks = (int) ceil($days / $chunkDays);
        $lock = Cache::lock(
            'garmin-sync:user:'.$user->id,
            $totalChunks * $this->chunkTimeout($chunkDays) + self::LOCK_SECONDS,
        );

        if (! $lock->get()) {
            return [
                'status' => 'error',
                'days' => $days,
                'chunks' => 0,
                'import_output' => null,
                'message' => 'Sinkronisasi Garmin sedang berjalan untuk akun ini. Coba lagi beberapa saat.',
            ];
        }

        try {
            return $this->doBackfill($user, $days, $chunkDays, $progress);
        } finally {
            $lock->release();
        }
    }

    /** Timeout proses python untuk satu chunk (minimum TIMEOUT_SECONDS). */
    private function chunkTimeout(int $chunkDays): int
    {
        return max(self::TIMEOUT_SECONDS, $chunkDays * self::BACKFILL_SECONDS_PER_DAY);
    }

    /**
     * @param  callable(array{chunk: int, chunks: int, days: int, offset: int}): void|null  $progress
     * @return array{status: 'success'|'error', days: int, chunks: int, import_output: ?string, message: ?string}
     */
    private function doBackfill(User $user, int $days, int $chunkDays, ?callable $progress): array
    {
        $connection = $user->garminConnection;

        if (! $connection) {
            return [
                'status' => 'error',
                'days' => $days,
                'chunks' => 0,
                'import_output' => null,
                'message' => 'Belum terhubung ke Garmin.',
            ];
        }

        $tokenStore = self::tokenStorePathForUser($user);
        $totalChunks = (int) ceil($days / $chunkDays);
        $offset = 0;
        $done = 0;
        $importOutputs = [];

        for ($chunk = 1; $chunk <= $totalChunks; $chunk++) {
            $windowDays = min($chunkDays, $days - $offset);

            if ($progress) {
                $progress([
                    'chunk' => $chunk,
                    'chunks' => $totalChunks,
                    'days' => $windowDays,
                    'offset' => $offset,
                ]);
            }

            $result = Process::path(base_path())
                ->timeout($this->chunkTimeout($windowDays))
                ->run([
                    self::pythonBinary(),
                    'scripts/garmin_sync.py',
                    '--days', (string) $windowDays,
                    '--offset', (string) $offset,
                    '--token-store', $tokenStore,
                ]);

            if ($result->failed()) {
                $message = trim($result->errorOutput()) ?: sprintf(
                    'Chunk %d/%d (offset %d) gagal; %d chunk sebelumnya tetap tersimpan.',
                    $chunk,
                    $totalChunks,
                    $offset,
                    $done,
                );

                $connection->update([
                    'last_synced_at' => now(),
                    'last_sync_status' => 'error',
                    'last_sync_message' => $message,
                ]);

                return [
                    'status' => 'error',
                    'days' => $days,
                    'chunks' => $done,
                    'import_output' => $this->joinImportOutputs($importOutputs),
                    'message' => $message,
                ];
            }

            $tmpFile = tempnam(sys_get_temp_dir(), 'garmin_backfill_');
            file_put_contents($tmpFile, $result->output());

            try {
                Artisan::call('garmin:import', ['path' => $tmpFile, '--user' => $user->id]);
                $importOutputs[] = trim(Artisan::output());
                Artisan::call('recovery:calculate', ['--days' => $windowDays, '--user' => $user->id]);
            } finally {
                @unlink($tmpFile);
            }

            $done++;
            $offset += $windowDays;
        }

        // Data baru sudah masuk — buang cache konteks AI agar pesan berikutnya
        // langsung memakai data terbaru.
        Cache::forget('ai-context:user:'.$user->id);

        $connection->update([
            'last_synced_at' => now(),
            'last_sync_status' => 'success',
            'last_sync_message' => null,
        ]);

        return [
            'status' => 'success',
            'days' => $days,
            'chunks' => $totalChunks,
            'import_output' => $this->joinImportOutputs($importOutputs),
            'message' => null,
        ];
    }

    /** @param  list<string>  $outputs */
    private function joinImportOutputs(array $outputs): ?string
    {
        $outputs = array_values(array_filter($outputs, static fn (string $out): bool => $out !== ''));

        return $outputs === [] ? null : implode("\n", $outputs);
    }

    /**
     * @return array{status: 'success'|'error', days: int, message: ?string, import_output: ?string}
     */
    private function doSync(User $user, int $days): array
    {
        $connection = $user->garminConnection;

        if (! $connection) {
            return [
                'status' => 'error',
                'days' => $days,
                'message' => 'Belum terhubung ke Garmin.',
                'import_output' => null,
            ];
        }

        $tokenStore = self::tokenStorePathForUser($user);

        $result = Process::path(base_path())
            ->timeout(self::TIMEOUT_SECONDS)
            ->run([
                self::pythonBinary(),
                'scripts/garmin_sync.py',
                '--days', (string) $days,
                '--token-store', $tokenStore,
            ]);

        if ($result->failed()) {
            $connection->update([
                'last_synced_at' => now(),
                'last_sync_status' => 'error',
                'last_sync_message' => trim($result->errorOutput()) ?: 'Gagal menjalankan sinkronisasi.',
            ]);

            return [
                'status' => 'error',
                'days' => $days,
                'message' => $connection->last_sync_message,
                'import_output' => null,
            ];
        }

        $tmpFile = tempnam(sys_get_temp_dir(), 'garmin_sync_');
        file_put_contents($tmpFile, $result->output());

        try {
            Artisan::call('garmin:import', ['path' => $tmpFile, '--user' => $user->id]);
            $importOutput = trim(Artisan::output());
            Artisan::call('recovery:calculate', ['--days' => $days, '--user' => $user->id]);

            // Fresh data just landed — drop any cached AI coach context so the
            // next message reflects the new data immediately.
            Cache::forget('ai-context:user:'.$user->id);
        } finally {
            @unlink($tmpFile);
        }

        $connection->update([
            'last_synced_at' => now(),
            'last_sync_status' => 'success',
            'last_sync_message' => null,
        ]);

        return [
            'status' => 'success',
            'days' => $days,
            'message' => null,
            'import_output' => $importOutput !== '' ? $importOutput : null,
        ];
    }
}
