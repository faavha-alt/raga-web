<?php

namespace App\Services\Auth;

use App\Models\User;
use App\Support\AvatarStorage;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\User as SocialiteUser;

/**
 * Menyambungkan identitas Google ke akun RAGA.
 *
 * Aturan yang dipegang di sini:
 *
 * 1. `sub` (google_id) adalah kunci utama. Klausa ini yang diperiksa lebih dulu
 *    karena tidak bisa dipalsukan dan tetap sama walau pengguna mengganti nama
 *    atau alamat emailnya di Google.
 * 2. Menautkan ke akun lama hanya boleh kalau Google menyatakan emailnya sudah
 *    terverifikasi. Kalau tidak, orang yang menguasai akun Google dengan email
 *    orang lain bisa mengambil alih akun RAGA-nya.
 * 3. Nama tampilan tidak pernah ditimpa setelah akun dibuat, supaya perubahan
 *    nama di Google tidak menghapus nama yang sudah diatur pengguna di RAGA.
 */
class GoogleAccountService
{
    /**
     * Batas ukuran foto profil yang mau diunduh, dalam byte.
     */
    private const MAX_AVATAR_BYTES = 2 * 1024 * 1024;

    public function __construct(private readonly AvatarStorage $avatars) {}

    /**
     * Kembalikan akun RAGA untuk identitas Google ini, buat bila belum ada.
     *
     * @throws GoogleAccountException
     */
    public function resolve(SocialiteUser $googleUser): User
    {
        try {
            return $this->resolveOnce($googleUser);
        } catch (UniqueConstraintViolationException $e) {
            // Terjadi kalau dua permintaan masuk hampir bersamaan untuk orang yang
            // sama (klik ganda, dua tab). Percobaan kedua akan menemukan baris yang
            // sudah dibuat percobaan pertama alih-alih menampilkan error ke pengguna.
            try {
                return $this->resolveOnce($googleUser);
            } catch (UniqueConstraintViolationException) {
                throw new GoogleAccountException(
                    'Pendaftaran bentrok dengan data lain. Coba masuk sekali lagi.',
                    previous: $e,
                );
            }
        }
    }

    /**
     * @throws GoogleAccountException
     */
    private function resolveOnce(SocialiteUser $googleUser): User
    {
        $googleId = trim((string) $googleUser->getId());
        $email = Str::lower(trim((string) $googleUser->getEmail()));
        $emailVerified = $this->googleSaysEmailIsVerified($googleUser);

        if ($googleId === '') {
            throw new GoogleAccountException('Google tidak mengirimkan identitas akun. Coba masuk sekali lagi.');
        }

        if (($user = $this->findByGoogleId($googleId)) !== null) {
            return $this->sync($user, $googleUser, $emailVerified);
        }

        if ($email === '') {
            throw new GoogleAccountException(
                'Akun Google-mu tidak membagikan alamat email, jadi kami tidak bisa membuat akun. '
                .'Izinkan akses email saat diminta, lalu coba lagi.',
            );
        }

        $existing = User::query()->where('email', $email)->first();

        if ($existing !== null) {
            if (! $emailVerified) {
                throw new GoogleAccountException(
                    'Email ini sudah terdaftar di RAGA, dan Google belum memverifikasinya. '
                    .'Masuk dulu memakai email dan password, lalu tautkan Google dari halaman profil.',
                );
            }

            $existing->google_id = $googleId;

            return $this->sync($existing, $googleUser, $emailVerified);
        }

        return $this->create($googleUser, $googleId, $email, $emailVerified);
    }

    private function findByGoogleId(string $googleId): ?User
    {
        return User::query()->where('google_id', $googleId)->first();
    }

    /**
     * Buat akun RAGA baru dari identitas Google.
     *
     * `password` sengaja dibiarkan NULL: akun ini memang tidak punya password,
     * dan User::hasPassword() memakai itu untuk melewati verifikasi
     * `current_password` yang tidak mungkin dipenuhi.
     */
    private function create(SocialiteUser $googleUser, string $googleId, string $email, bool $emailVerified): User
    {
        $name = $this->displayName($googleUser, $email);

        $user = new User;
        $user->name = $name;
        $user->username = $this->uniqueUsername($name, $email);
        $user->email = $email;
        $user->password = null;
        $user->email_verified_at = $emailVerified ? now() : null;
        $user->google_id = $googleId;
        $user->avatar_path = $this->downloadAvatar($googleUser->getAvatar());
        $user->save();

        return $user;
    }

    /**
     * Lengkapi akun yang sudah ada: tautkan Google, tandai email terverifikasi,
     * dan pasang foto profil bila pengguna belum pernah mengunggahnya sendiri.
     */
    private function sync(User $user, SocialiteUser $googleUser, bool $emailVerified): User
    {
        if ($emailVerified && $user->email_verified_at === null) {
            $user->email_verified_at = now();
        }

        if ($user->avatar_path === null) {
            $user->avatar_path = $this->downloadAvatar($googleUser->getAvatar());
        }

        if ($user->isDirty()) {
            $user->save();
        }

        return $user;
    }

    private function displayName(SocialiteUser $googleUser, string $email): string
    {
        $name = preg_replace('/\s+/u', ' ', trim((string) $googleUser->getName())) ?? '';
        $name = trim($name);

        if ($name === '') {
            $name = Str::before($email, '@');
        }

        return Str::limit($name, 255, '');
    }

    /**
     * Cari username yang belum dipakai, mengikuti aturan yang sama dengan
     * form registrasi: huruf kecil, angka, titik, garis bawah, tanda hubung,
     * panjang 3–30 karakter.
     */
    private function uniqueUsername(string $name, string $email): string
    {
        $base = $this->usernameBase($name);

        // Nama yang seluruhnya non-latin (mis. aksara Jepang) menyisakan string
        // kosong; bagian lokal email biasanya masih bisa dipakai.
        if (! preg_match('/[a-z0-9]/', $base)) {
            $base = $this->usernameBase(Str::before($email, '@'));
        }

        if (! preg_match('/[a-z0-9]/', $base)) {
            $base = 'atlet';
        }

        // Sisakan ruang untuk akhiran angka agar hasil akhir tetap <= 30 karakter.
        $base = Str::substr($base, 0, 26);

        while (strlen($base) < 3) {
            $base .= (string) random_int(0, 9);
        }

        $candidate = $base;

        for ($suffix = 2; User::query()->where('username', $candidate)->exists(); $suffix++) {
            // Nama yang sangat umum tidak boleh membuat pencarian ini tak terbatas.
            if ($suffix > 50) {
                return Str::substr($base, 0, 20).Str::lower(Str::random(9));
            }

            $candidate = Str::substr($base, 0, 30 - strlen((string) $suffix)).$suffix;
        }

        return $candidate;
    }

    private function usernameBase(string $value): string
    {
        $ascii = Str::lower(Str::ascii($value));
        $clean = preg_replace('/[^a-z0-9._-]+/', '', $ascii) ?? '';

        // Buang pemisah di kedua ujung supaya tidak menghasilkan "@." atau "@-".
        return trim($clean, '._-');
    }

    /**
     * Google menaruh klaim `email_verified` di data mentah Socialite. Kalau
     * klaimnya tidak ada sama sekali, anggap belum terverifikasi — jangan menebak.
     */
    private function googleSaysEmailIsVerified(SocialiteUser $googleUser): bool
    {
        return (bool) ($googleUser->getRaw()['verified_email'] ?? false);
    }

    /**
     * Unduh foto profil Google ke penyimpanan lokal milik RAGA.
     *
     * Sengaja tidak di-hotlink: kalau URL Google dipakai langsung sebagai `src`,
     * setiap pengunjung yang membuka profil akan memanggil server Google dan
     * membocorkan alamat IP-nya. Kegagalan unduhan tidak fatal — pengguna cukup
     * tampil dengan inisial, sama seperti sebelum punya avatar.
     */
    private function downloadAvatar(?string $url): ?string
    {
        if ($url === null || trim($url) === '') {
            return null;
        }

        try {
            $response = Http::timeout(10)->get($this->largerAvatarUrl($url));
        } catch (ConnectionException) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $contents = $response->body();

        if ($contents === '' || strlen($contents) > self::MAX_AVATAR_BYTES) {
            return null;
        }

        // Percayai isi berkasnya, bukan header atau URL yang bisa berisi apa pun.
        $info = @getimagesizefromstring($contents);

        if ($info === false) {
            return null;
        }

        $extension = match ($info[2]) {
            IMAGETYPE_PNG => 'png',
            IMAGETYPE_JPEG => 'jpg',
            IMAGETYPE_WEBP => 'webp',
            default => null,
        };

        if ($extension === null) {
            return null;
        }

        return $this->avatars->storeBinary($contents, $extension);
    }

    /**
     * Google mengirim foto pada ukuran kecil (`=s96-c`). Minta versi yang cukup
     * tajam untuk layar beretina, tapi jangan sentuh URL yang bentuknya lain.
     */
    private function largerAvatarUrl(string $url): string
    {
        return preg_replace('/=s\d+(-c)?$/', '=s256-c', $url) ?? $url;
    }
}
