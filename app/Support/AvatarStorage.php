<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/**
 * Penyimpanan avatar.
 *
 * File ditulis ke `public/uploads/avatars` (bukan disk `public` + symlink)
 * karena skrip deploy CloudPanel tidak menjalankan `php artisan storage:link`,
 * sehingga path ini dijamin bisa diakses tanpa langkah manual di server.
 * Direktori ini untracked sehingga aman terhadap `git reset --hard` saat deploy.
 */
class AvatarStorage
{
    public const DIRECTORY = 'uploads/avatars';

    /**
     * Simpan file avatar dan kembalikan path relatif terhadap `public/`.
     *
     * Nama file selalu dibuat acak dan ekstensinya diambil dari tipe MIME yang
     * terdeteksi, bukan dari nama file kiriman klien.
     */
    public function store(UploadedFile $file): string
    {
        $extension = strtolower($file->extension() ?: 'jpg');
        $filename = Str::random(40).'.'.$extension;

        $directory = public_path(self::DIRECTORY);

        if (! is_dir($directory)) {
            mkdir($directory, 0o755, true);
        }

        $file->move($directory, $filename);

        return self::DIRECTORY.'/'.$filename;
    }

    /**
     * Hapus avatar lama bila ada. Aman dipanggil dengan null.
     */
    public function delete(?string $path): void
    {
        if ($path === null || $path === '') {
            return;
        }

        $full = public_path($path);

        // Cegah penghapusan di luar direktori upload bila path di DB pernah ternoda.
        $real = realpath($full);
        $base = realpath(public_path(self::DIRECTORY));

        if ($real === false || $base === false || ! str_starts_with($real, $base)) {
            return;
        }

        if (is_file($real)) {
            unlink($real);
        }
    }
}
