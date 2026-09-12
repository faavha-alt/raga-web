<?php

namespace App\Services\Suunto;

use App\Models\User;
use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

/**
 * Pembungkus CLI `suuntool` (<https://github.com/tajchert/suuntool>) — klien
 * tidak resmi ke backend aplikasi Suunto (Sports-Tracker API), analog
 * `garminconnect` untuk Garmin.
 *
 * Kenapa dipakai: Suunto Cloud API resmi tertutup untuk pemakaian pribadi
 * (wajib Partner Program) dan tidak menyediakan data tidur. Jalur ini memakai
 * backend yang sama dengan aplikasi Suunto di ponsel, jadi tersedia
 * `workouts list/get/sml/fit` plus wellness `sleep/activity/recovery`.
 *
 * Keamanan & privasi: password TIDAK pernah disimpan — hanya dikirim sekali ke
 * proses `login` lewat stdin. Sesi hasil login hidup di berkas per user
 * (`storage/app/suunto_sessions/<id>/session.json`, mode 0600) dan diteruskan
 * ke setiap pemanggilan lewat env `SUUNTOOL_SESSION_FILE`.
 *
 * Risiko yang harus disadari pemakai: API ini privat, kontraknya bisa berubah
 * sewaktu-waktu, dan pemakaiannya berpotensi melanggar ToS Suunto — hanya untuk
 * data akun sendiri.
 */
class SuuntoToolClient
{
    /** Batas aman: satu list + SML beberapa workout per sync. */
    private const DEFAULT_LIMIT = 20;

    public function __construct(private User $user) {}

    /**
     * Path binary. Urutan pencarian: path absolut dari config, `~/.local/bin`
     * (tempat deploy memasangnya), lalu `storage/app/bin` yang ikut deploy dan
     * tidak bergantung pada `HOME` proses PHP-FPM.
     */
    public static function binary(): string
    {
        $configured = (string) config('services.suunto.binary', 'suuntool');

        if (str_contains($configured, '/')) {
            return $configured;
        }

        $home = getenv('HOME') ?: null;

        $candidates = [
            $home ? $home.'/.local/bin/'.$configured : null,
            storage_path('app/bin/'.$configured),
        ];

        foreach ($candidates as $candidate) {
            if ($candidate && is_executable($candidate)) {
                return $candidate;
            }
        }

        return $configured;
    }

    /** Nama mode yang disimpan di `suunto_connections.auth_mode`. */
    public static function authMode(): string
    {
        return 'password';
    }

    /** Hasil pengecekan binary, di-cache per nama path (bukan sekali untuk semua). */
    private static array $availability = [];

    /**
     * Binary siap dipakai? Hasilnya di-cache per request supaya tidak
     * menjalankan proses berkali-kali di satu halaman.
     */
    public static function isAvailable(): bool
    {
        $binary = self::binary();

        if (array_key_exists($binary, self::$availability)) {
            return self::$availability[$binary];
        }

        if (str_contains($binary, '/')) {
            return self::$availability[$binary] = is_executable($binary);
        }

        $result = Process::timeout(10)->run(['which', $binary]);

        return self::$availability[$binary] = $result->successful() && trim($result->output()) !== '';
    }

    /** Lokasi berkas sesi milik satu pengguna (dibuat bila belum ada). */
    public static function sessionPathForUser(User $user): string
    {
        $directory = storage_path('app/suunto_sessions/'.$user->id);

        if (! is_dir($directory)) {
            File::makeDirectory($directory, 0700, true, true);
        }

        return $directory.'/session.json';
    }

    public function hasSession(): bool
    {
        return is_file(self::sessionPathForUser($this->user));
    }

    /**
     * Login memakai kredensial Suunto App; password hanya lewat stdin.
     *
     * @return array{status: 'success'|'error', username: ?string, message: ?string}
     */
    public function login(string $email, string $password): array
    {
        $result = $this->run(['login', '--email', $email, '--password-stdin'], 60, $password);

        if ($result->failed()) {
            return [
                'status' => 'error',
                'username' => null,
                'message' => $this->errorFrom($result),
            ];
        }

        return [
            'status' => 'success',
            'username' => $this->whoami(),
            'message' => null,
        ];
    }

    public function whoami(): ?string
    {
        $result = $this->run(['whoami', '--format', 'json'], 30);

        if ($result->failed()) {
            return null;
        }

        $data = json_decode(trim($result->output()), true);

        if (! is_array($data)) {
            return null;
        }

        foreach (['username', 'email', 'name'] as $key) {
            if (isset($data[$key]) && is_string($data[$key]) && $data[$key] !== '') {
                return $data[$key];
            }
        }

        return null;
    }

    /**
     * Daftar workout sejak tanggal tertentu (`workouts list --since`).
     *
     * @return list<array<string, mixed>>
     */
    public function workoutsSince(string $since, ?int $limit = null): array
    {
        $result = $this->run([
            'workouts', 'list',
            '--since', $since,
            '--limit', (string) ($limit ?? (int) config('services.suunto.sync_limit', self::DEFAULT_LIMIT)),
            '--format', 'json',
        ], 120);

        if ($result->failed()) {
            throw new SuuntoApiException($this->errorFrom($result));
        }

        $payload = json_decode(trim($result->output()), true);

        if (! is_array($payload)) {
            throw new SuuntoApiException('Output `suuntool workouts list` bukan JSON.');
        }

        $data = $this->unwrapEnvelope($payload);

        if (! is_array($data)) {
            throw new SuuntoApiException('Output `suuntool workouts list` tidak memuat daftar workout.');
        }

        if (array_is_list($data)) {
            return $data;
        }

        foreach (['workouts', 'items', 'data'] as $key) {
            if (isset($data[$key]) && is_array($data[$key])) {
                return array_values($data[$key]);
            }
        }

        throw new SuuntoApiException('Output `suuntool workouts list` tidak memuat daftar workout.');
    }

    /**
     * Data sampel satu workout (`workouts sml`, JSON besar ~5 MB).
     *
     * @return array<string, mixed>|null
     */
    public function workoutSamples(string $key): ?array
    {
        $result = $this->run(['workouts', 'sml', $key], 180);

        if ($result->failed()) {
            throw new SuuntoApiException($this->errorFrom($result));
        }

        $payload = json_decode(trim($result->output()), true);

        if (! is_array($payload)) {
            return null;
        }

        $data = $this->unwrapEnvelope($payload);

        return is_array($data) ? $data : null;
    }

    /**
     * suuntool membungkus respons sebagai `{"error":…,"payload":…,"metadata":…}`.
     * Bila `error` berisi pesan, itu diterjemahkan menjadi exception.
     *
     * @param  array<string, mixed>  $decoded
     */
    private function unwrapEnvelope(array $decoded): mixed
    {
        if (isset($decoded['error']) && is_array($decoded['error'])) {
            $message = $decoded['error']['message'] ?? null;

            if (is_string($message) && $message !== '') {
                throw new SuuntoApiException($message);
            }
        }

        return $decoded['payload'] ?? $decoded;
    }

    /**
     * NDJSON wellness sleep (`wellness sleep --since`), sudah didekode per baris.
     *
     * @return list<array<string, mixed>>
     */
    public function sleepSince(string $since): array
    {
        $result = $this->run(['wellness', 'sleep', '--since', $since], 120);

        if ($result->failed()) {
            throw new SuuntoApiException($this->errorFrom($result));
        }

        $entries = [];

        foreach (preg_split('/\R/', trim($result->output())) ?: [] as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            $decoded = json_decode($line, true);

            if (is_array($decoded)) {
                $entries[] = $decoded;
            }
        }

        return $entries;
    }

    /**
     * Jalankan binary dengan sesi milik user ini; env dipakai agar berkas sesi
     * tidak pernah keluar dari direktori pengguna.
     *
     * @param  list<string>  $arguments
     */
    private function run(array $arguments, int $timeout, ?string $stdin = null): ProcessResult
    {
        return Process::env([
            'SUUNTOOL_SESSION_FILE' => self::sessionPathForUser($this->user),
            'SUUNTOOL_FORMAT' => 'json',
            'NO_COLOR' => '1',
        ])
            ->timeout($timeout)
            ->input($stdin ?? '')
            ->run(array_merge([self::binary()], $arguments));
    }

    /** Pesan error yang berguna: stderr, atau baris JSON `{"error":{...}}` di stdout. */
    private function errorFrom(ProcessResult $result): string
    {
        $stderr = trim($result->errorOutput());
        $stdout = trim($result->output());

        $decoded = json_decode($stdout, true);

        if (is_array($decoded) && isset($decoded['error']) && is_array($decoded['error'])) {
            $message = $decoded['error']['message'] ?? null;
            $hint = $decoded['error']['hint'] ?? null;

            if (is_string($message) && $message !== '') {
                return $message.($hint ? ' ('.$hint.')' : '');
            }
        }

        if ($stderr !== '') {
            return mb_substr($stderr, 0, 300);
        }

        return 'suuntool keluar dengan kode '.$result->exitCode().'.';
    }
}
