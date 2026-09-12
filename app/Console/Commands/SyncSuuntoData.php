<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Suunto\SuuntoSyncService;
use Illuminate\Console\Command;

/**
 * Menarik workout dari Suunto Cloud API (read-only) untuk satu atau semua
 * pengguna yang sudah menghubungkan akun Suunto. Cocok dipasang di cron,
 * bersanding dengan `garmin:import` untuk pengguna Garmin.
 */
class SyncSuuntoData extends Command
{
    protected $signature = 'suunto:sync {--user= : Target user ID (defaults to every user with a Suunto connection)} {--days=7 : Berapa hari ke belakang yang ditarik}';

    protected $description = 'Sync workouts from Suunto Cloud API into RAGA (mapped to the same tables as Garmin)';

    public function handle(SuuntoSyncService $sync): int
    {
        $days = (int) $this->option('days');

        if ($this->option('user')) {
            $users = User::whereKey($this->option('user'))->get();
        } else {
            $users = User::whereHas('suuntoConnection')->get();
        }

        if ($users->isEmpty()) {
            $this->warn('Tidak ada pengguna dengan koneksi Suunto.');

            return self::SUCCESS;
        }

        $failed = false;

        foreach ($users as $user) {
            $result = $sync->syncForUser($user, $days);

            if ($result['status'] === 'error') {
                $failed = true;
                $this->error(sprintf('%s: %s', $user->email, $result['message'] ?? 'gagal'));

                continue;
            }

            $this->info(sprintf(
                '%s: %d workout diimpor (%d dilewati, %d hari).',
                $user->email,
                $result['imported'],
                $result['skipped'],
                $result['days'],
            ));
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
