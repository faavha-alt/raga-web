<?php

namespace App\Services\Suunto;

use App\Models\SuuntoConnection;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Klien tipis untuk Suunto Cloud API (apizone.suunto.com).
 *
 * Alur OAuth2 (authorization code) dan bentuk permintaan token diverifikasi dari
 * dokumentasi resmi portal Suunto: token endpoint memakai HTTP Basic
 * (client_id:client_secret) + form `grant_type`/`code`/`redirect_uri`, sedangkan
 * setiap panggilan API memerlukan dua header — `Authorization: Bearer <jwt>` dan
 * `Ocp-Apim-Subscription-Key`.
 *
 * Catatan kuota: Suunto membatasi jumlah panggilan API per minggu. Karena itu
 * daftar workout diambil satu kali per rentang tanggal dengan parameter
 * `extensions` (stream HR/GPS/kecepatan ikut dalam respons yang sama), bukan
 * satu panggilan per aktivitas.
 */
class SuuntoApiClient
{
    public function __construct(private ?SuuntoConnection $connection = null) {}

    /** Kredensial OAuth + subscription key siap dipakai? */
    public static function isConfigured(): bool
    {
        return self::hasOAuthCredentials() && filled(config('services.suunto.subscription_key'));
    }

    /** Hanya butuh client id/secret (mis. untuk menampilkan tombol "Hubungkan"). */
    public static function hasOAuthCredentials(): bool
    {
        return filled(config('services.suunto.client_id'))
            && filled(config('services.suunto.client_secret'));
    }

    public function authorizationUrl(string $state): string
    {
        if (! self::hasOAuthCredentials()) {
            throw new SuuntoApiException('SUUNTO_CLIENT_ID / SUUNTO_CLIENT_SECRET belum diisi.');
        }

        return rtrim((string) config('services.suunto.oauth_base'), '/').'/oauth/authorize?'.http_build_query([
            'response_type' => 'code',
            'client_id' => config('services.suunto.client_id'),
            'redirect_uri' => self::redirectUri(),
            'state' => $state,
        ]);
    }

    public static function redirectUri(): string
    {
        $configured = (string) config('services.suunto.redirect');

        return str_starts_with($configured, 'http') ? $configured : url($configured);
    }

    /**
     * Tukar authorization code menjadi token.
     *
     * @return array{access_token: string, refresh_token: ?string, expires_at: CarbonImmutable, scope: ?string, username: ?string}
     */
    public static function exchangeAuthorizationCode(string $code): array
    {
        return self::tokenRequest([
            'grant_type' => 'authorization_code',
            'redirect_uri' => self::redirectUri(),
            'code' => $code,
        ]);
    }

    /**
     * @return array{access_token: string, refresh_token: ?string, expires_at: CarbonImmutable, scope: ?string, username: ?string}
     */
    public static function refreshTokens(string $refreshToken): array
    {
        return self::tokenRequest([
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
        ]);
    }

    /**
     * Pastikan token di koneksi masih berlaku; refresh bila sudah/akan kedaluwarsa.
     */
    public function ensureFreshToken(): void
    {
        $connection = $this->connectionOrFail();

        if (! $connection->isExpired()) {
            return;
        }

        if (! filled($connection->refresh_token)) {
            throw new SuuntoApiException('Token Suunto sudah kedaluwarsa dan tidak ada refresh token. Hubungkan ulang akun Suunto.');
        }

        $tokens = self::refreshTokens((string) $connection->refresh_token);
        $connection->update(self::tokenAttributes($tokens, $connection));
    }

    /** GET satu endpoint API; path relatif terhadap `api_base`. */
    public function get(string $path, array $params = []): array
    {
        $connection = $this->connectionOrFail();
        $this->ensureFreshToken();

        $response = $this->send($path, $params, (string) $connection->access_token);

        // Token bisa dicabut di sisi Suunto sebelum `expires_at` tercapai —
        // sekali 401, paksa refresh lalu ulangi satu kali.
        if ($response->status() === 401 && filled($connection->refresh_token)) {
            $tokens = self::refreshTokens((string) $connection->refresh_token);
            $connection->update(self::tokenAttributes($tokens, $connection));

            $response = $this->send($path, $params, (string) $connection->access_token);
        }

        if ($response->failed()) {
            throw new SuuntoApiException(self::errorMessage($response));
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            throw new SuuntoApiException('Respons Suunto bukan JSON objek (HTTP '.$response->status().').');
        }

        return $payload;
    }

    /** Daftar workout (satu panggilan untuk satu rentang tanggal). */
    public function workouts(array $params = []): array
    {
        return $this->get((string) config('services.suunto.api_path', '/v3/workouts'), $params);
    }

    public function workout(string $workoutKey, array $params = []): array
    {
        return $this->get(rtrim((string) config('services.suunto.api_path', '/v3/workouts'), '/').'/'.rawurlencode($workoutKey), $params);
    }

    /**
     * Ekstrak username akun Suunto dari klaim `user` pada JWT (tanpa verifikasi
     * tanda tangan — hanya metadata untuk ditampilkan, bukan untuk otorisasi).
     */
    public static function usernameFromToken(string $jwt): ?string
    {
        $parts = explode('.', $jwt);

        if (count($parts) < 2) {
            return null;
        }

        $decoded = base64_decode(strtr($parts[1], '-_', '+/'), true);

        if ($decoded === false) {
            return null;
        }

        $payload = json_decode($decoded, true);

        if (! is_array($payload)) {
            return null;
        }

        foreach (['user', 'username', 'sub'] as $claim) {
            if (isset($payload[$claim]) && is_string($payload[$claim]) && $payload[$claim] !== '') {
                return $payload[$claim];
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{access_token: string, refresh_token: ?string, expires_at: CarbonImmutable, scope: ?string, username: ?string}
     */
    private static function tokenRequest(array $payload): array
    {
        if (! self::hasOAuthCredentials()) {
            throw new SuuntoApiException('SUUNTO_CLIENT_ID / SUUNTO_CLIENT_SECRET belum diisi.');
        }

        try {
            $response = Http::asForm()
                ->withBasicAuth((string) config('services.suunto.client_id'), (string) config('services.suunto.client_secret'))
                ->timeout((int) config('services.suunto.timeout', 30))
                ->post(rtrim((string) config('services.suunto.oauth_base'), '/').'/oauth/token', $payload);
        } catch (ConnectionException $e) {
            throw new SuuntoApiException('Tidak bisa menghubungi server OAuth Suunto: '.$e->getMessage());
        }

        if ($response->failed()) {
            throw new SuuntoApiException(self::errorMessage($response));
        }

        $data = $response->json();

        if (! is_array($data) || empty($data['access_token'])) {
            throw new SuuntoApiException('Respons token Suunto tidak memuat access_token.');
        }

        $accessToken = (string) $data['access_token'];
        $expiresIn = is_numeric($data['expires_in'] ?? null) ? (int) $data['expires_in'] : null;

        return [
            'access_token' => $accessToken,
            'refresh_token' => isset($data['refresh_token']) && is_string($data['refresh_token']) ? $data['refresh_token'] : null,
            'expires_at' => $expiresIn !== null
                ? CarbonImmutable::now()->addSeconds($expiresIn)
                : CarbonImmutable::now()->addHours(24),
            'scope' => isset($data['scope']) && is_string($data['scope']) ? $data['scope'] : null,
            'username' => self::usernameFromToken($accessToken),
        ];
    }

    /**
     * Susun atribut model dari hasil token (pertahankan refresh token lama bila
     * respons refresh tidak mengirimkannya lagi).
     *
     * @param  array{access_token: string, refresh_token: ?string, expires_at: CarbonImmutable, scope: ?string, username: ?string}  $tokens
     * @return array<string, mixed>
     */
    private static function tokenAttributes(array $tokens, SuuntoConnection $connection): array
    {
        return [
            'access_token' => $tokens['access_token'],
            'refresh_token' => $tokens['refresh_token'] ?? $connection->refresh_token,
            'expires_at' => $tokens['expires_at'],
            'scope' => $tokens['scope'] ?? $connection->scope,
            'suunto_username' => $tokens['username'] ?? $connection->suunto_username,
        ];
    }

    private function send(string $path, array $params, string $accessToken): Response
    {
        try {
            return Http::withToken($accessToken)
                ->withHeaders(['Ocp-Apim-Subscription-Key' => (string) config('services.suunto.subscription_key')])
                ->acceptJson()
                ->timeout((int) config('services.suunto.timeout', 30))
                ->get(rtrim((string) config('services.suunto.api_base'), '/').'/'.ltrim($path, '/'), $params);
        } catch (ConnectionException $e) {
            throw new SuuntoApiException('Tidak bisa menghubungi Suunto Cloud API: '.$e->getMessage());
        }
    }

    private function connectionOrFail(): SuuntoConnection
    {
        if (! $this->connection) {
            throw new SuuntoApiException('Belum terhubung ke Suunto.');
        }

        if (! self::isConfigured()) {
            throw new SuuntoApiException('Kredensial Suunto (client id/secret/subscription key) belum lengkap.');
        }

        return $this->connection;
    }

    private static function errorMessage(Response $response): string
    {
        $body = trim((string) $response->body());
        $snippet = $body === '' ? '' : ' — '.mb_substr($body, 0, 200);

        try {
            $json = $response->json();
            if (is_array($json)) {
                foreach (['message', 'error_description', 'error', 'detail'] as $key) {
                    if (isset($json[$key]) && is_string($json[$key])) {
                        $snippet = ' — '.$json[$key];
                        break;
                    }
                }
            }
        } catch (Throwable) {
            // Biarkan snippet mentah dari body.
        }

        return 'Suunto API mengembalikan HTTP '.$response->status().$snippet;
    }
}
