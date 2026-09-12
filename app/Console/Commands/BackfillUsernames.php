<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Isi username untuk pengguna lama yang belum punya.
 *
 * Kolom `username` sengaja ditambahkan nullable karena tabel `users` di produksi
 * sudah terisi. Perintah ini melengkapi baris lama tanpa mengunci skema dalam
 * satu migrasi berisiko.
 */
class BackfillUsernames extends Command
{
    protected $signature = 'users:backfill-usernames {--dry-run : Tampilkan rencana tanpa menulis ke database}';

    protected $description = 'Buat username untuk pengguna yang belum memilikinya, berdasarkan nama dan email';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $users = User::query()->whereNull('username')->orderBy('id')->get();

        if ($users->isEmpty()) {
            $this->info('Semua pengguna sudah punya username.');

            return self::SUCCESS;
        }

        foreach ($users as $user) {
            $candidate = $this->candidateFor($user);

            $this->line(sprintf('#%d %s -> @%s', $user->id, $user->email, $candidate));

            if (! $dryRun) {
                $user->forceFill(['username' => $candidate])->save();
            }
        }

        $this->info($dryRun
            ? sprintf('%d pengguna akan diperbarui (dry-run, tidak ada perubahan).', $users->count())
            : sprintf('%d pengguna diperbarui.', $users->count()));

        return self::SUCCESS;
    }

    /**
     * Turunkan username unik dari nama, lalu email, lalu id sebagai jalan terakhir.
     */
    private function candidateFor(User $user): string
    {
        $base = Str::slug((string) $user->name, '');
        $base = Str::lower(preg_replace('/[^a-z0-9]/i', '', $base) ?? '');

        if (mb_strlen($base) < 3) {
            $base = Str::lower(preg_replace('/[^a-z0-9]/i', '', Str::before((string) $user->email, '@')) ?? '');
        }

        if (mb_strlen($base) < 3) {
            $base = 'atlet';
        }

        $base = mb_substr($base, 0, 24);

        $candidate = $base;
        $suffix = 1;

        while (User::query()->where('username', $candidate)->exists()) {
            $suffix++;
            $candidate = mb_substr($base, 0, 24).$suffix;
        }

        return $candidate;
    }
}
