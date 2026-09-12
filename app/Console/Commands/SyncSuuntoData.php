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
    protected $signature = 'suunto:sync
        {--user= : Target user ID (defaults to every user with a Suunto connection)}
        {--days=7 : Berapa hari ke belakang yang ditarik (boleh 730 untuk backfill 2 tahun)}
        {--samples=auto : Sampel per-detik: auto (hanya N hari terakhir), all, atau none}';

    protected $description = 'Sync workouts from Suunto Cloud API into RAGA (mapped to the same tables as Garmin)';

    public function handle(SuuntoSyncService $sync): int
    {
        $days = (int) $this->option('days');
        $samples = (string) $this->option('samples');

        if ($this->option('user')) {
            $users = User::whereKey($this->option('user'))->get();
        } else {
            $users = User::whereHas('suuntoConnection')->get();
        }

        if ($users->isEmpty()) {
            $this->warn('Tidak ada pengguna dengan koneksi Suunto.');

            return self::SUCCESS;
        }

        if ($days > SuuntoSyncService::STREAM_DAYS) {
            $this->info(sprintf(
                'Mode backfill: %d hari (daftar diambil dengan --stream), sampel: %s.',
                $days,
                $samples,
            ));
        }

        $failed = false;

        foreach ($users as $user) {
            $this->info(sprintf('Menarik data untuk %s…', $user->email));
            $startedAt = microtime(true);

            $result = $sync->syncForUser($user, $days, $samples);

            if ($result['status'] === 'error') {
                $failed = true;
                $this->error(sprintf('%s: %s', $user->email, $result['message'] ?? 'gagal'));

                continue;
            }

            $this->info(sprintf(
                '%s: %d workout diimpor, %d dilewati (%d hari, %.1f menit).',
                $user->email,
                $result['imported'],
                $result['skipped'],
                $result['days'],
                (microtime(true) - $startedAt) / 60,
            ));
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
