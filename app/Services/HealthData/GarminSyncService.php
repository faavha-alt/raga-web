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
