<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\HealthData\GarminSyncService;
use Illuminate\Console\Command;

/**
 * Menyinkronkan data Garmin untuk satu atau semua pengguna. Rentang pendek
 * (<= 30 hari) memakai syncForUser() seperti tombol UI; rentang panjang
 * di-backfill per chunk supaya tidak ada satu proses yang kehabisan timeout.
 */
class SyncGarminData extends Command
{
    protected $signature = 'garmin:sync {--user= : Target user ID (defaults to every user with a Garmin connection)} {--days=2 : Berapa hari ke belakang yang ditarik} {--chunk=60 : Ukuran chunk untuk backfill (hari)}';

    protected $description = 'Sync Garmin data for a user (or every connected user); long ranges are chunked into a safe backfill';

    public function handle(GarminSyncService $sync): int
    {
        $days = max(1, (int) $this->option('days'));
        $chunk = max(1, (int) $this->option('chunk'));

        $users = $this->option('user')
            ? User::whereKey($this->option('user'))->get()
            : User::whereHas('garminConnection')->get();

        if ($users->isEmpty()) {
            $this->warn('Tidak ada pengguna dengan koneksi Garmin.');

            return self::SUCCESS;
        }

        $failed = false;

        foreach ($users as $user) {
            if ($days <= 30) {
                $result = $sync->syncForUser($user, $days);

                if (! $this->report($user, $result, 'sync')) {
                    $failed = true;
                }

                continue;
            }

            $this->info(sprintf('%s: backfill %d hari dalam chunk %d hari.', $user->email, $days, $chunk));

            $result = $sync->backfill($user, $days, $chunk, function (array $info) use ($user): void {
                $this->line(sprintf(
                    '%s: chunk %d/%d — %d hari (offset %d)',
                    $user->email,
                    $info['chunk'],
                    $info['chunks'],
                    $info['days'],
                    $info['offset'],
                ));
            });

            if (! $this->report($user, $result, 'backfill')) {
                $failed = true;
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    /** @param array{status: 'success'|'error', days: int, message: ?string} $result */
    private function report(User $user, array $result, string $mode): bool
    {
        if ($result['status'] === 'error') {
            $this->error(sprintf('%s: %s gagal — %s', $user->email, $mode, $result['message'] ?? 'error'));

            return false;
        }

        $this->info(sprintf('%s: %s selesai (%d hari).', $user->email, $mode, $result['days']));

        return true;
    }
}
