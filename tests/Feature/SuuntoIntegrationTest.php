<?php

namespace Tests\Feature;

use App\Models\SuuntoConnection;
use App\Models\User;
use App\Models\Workout;
use App\Services\Suunto\SuuntoSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Integrasi Suunto Cloud API (read-only): alur OAuth2, penyimpanan token,
 * sinkronisasi workout ke tabel yang sama dengan Garmin, dan penanganan error.
 * Semua panggilan jaringan dipalsukan — tidak ada kredensial Suunto di CI.
 */
class SuuntoIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.suunto.client_id' => 'test-client',
            'services.suunto.client_secret' => 'test-secret',
            'services.suunto.subscription_key' => 'test-key',
            'services.suunto.oauth_base' => 'https://cloudapi-oauth.suunto.com',
            'services.suunto.api_base' => 'https://cloudapi.suunto.com',
            'services.suunto.api_path' => '/v3/workouts',
            'services.suunto.redirect' => '/settings/suunto/callback',
        ]);
    }

    public function test_settings_page_shows_the_connect_card_when_configured(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('settings.suunto.show'))
            ->assertOk()
            ->assertSee('Hubungkan Suunto')
            ->assertDontSee('Kredensial Suunto belum diisi');
    }

    public function test_settings_page_warns_when_credentials_are_missing(): void
    {
        config(['services.suunto.client_id' => null, 'services.suunto.client_secret' => null]);

        $this->actingAs(User::factory()->create())
            ->get(route('settings.suunto.show'))
            ->assertOk()
            ->assertSee('Kredensial Suunto belum diisi')
            ->assertSee('apizone.suunto.com', false)
            ->assertDontSee('Hubungkan Suunto');
    }

    public function test_connect_redirects_to_suunto_with_a_state(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('settings.suunto.connect'))
            ->assertRedirectContains('https://cloudapi-oauth.suunto.com/oauth/authorize?')
            ->assertRedirectContains('client_id=test-client')
            ->assertRedirectContains('redirect_uri='.urlencode(url('/settings/suunto/callback')))
            ->assertSessionHas('suunto_oauth_state');
    }

    public function test_callback_rejects_a_mismatched_state(): void
    {
        $this->actingAs(User::factory()->create())
            ->withSession(['suunto_oauth_state' => 'expected-state'])
            ->get(route('settings.suunto.callback', ['code' => 'abc', 'state' => 'other-state']))
            ->assertRedirect(route('settings.suunto.show'))
            ->assertSessionHasErrors('suunto');
    }

    public function test_callback_stores_tokens_and_reads_the_username_from_the_jwt(): void
    {
        $user = User::factory()->create();

        Http::fake([
            'cloudapi-oauth.suunto.com/*' => Http::response($this->tokenSet('athlete@suunto')),
        ]);

        $this->actingAs($user)
            ->withSession(['suunto_oauth_state' => 'the-state'])
            ->get(route('settings.suunto.callback', ['code' => 'code-123', 'state' => 'the-state']))
            ->assertRedirect(route('settings.suunto.show'))
            ->assertSessionHas('status');

        $connection = $user->fresh()->suuntoConnection;

        $this->assertNotNull($connection);
        $this->assertSame('athlete@suunto', $connection->suunto_username);
        $this->assertSame($this->accessToken('athlete@suunto'), $connection->access_token);
        $this->assertSame('refresh-token-1', $connection->refresh_token);
        $this->assertTrue($connection->expires_at->isFuture());

        Http::assertSent(fn ($request) => str_contains($request->url(), '/oauth/token')
            && $request['grant_type'] === 'authorization_code'
            && $request['code'] === 'code-123');
    }

    public function test_sync_imports_workouts_with_the_suunto_source(): void
    {
        $user = $this->connectedUser();

        Http::fake([
            'cloudapi.suunto.com/v3/workouts*' => Http::response(['workouts' => [$this->workoutPayload()]]),
        ]);

        $result = app(SuuntoSyncService::class)->syncForUser($user, 7);

        $this->assertSame('success', $result['status']);
        $this->assertSame(1, $result['imported']);

        $workout = Workout::where('user_id', $user->id)->firstOrFail();

        $this->assertSame('suunto', $workout->source);
        $this->assertSame('running', $workout->type);
        $this->assertSame(10000.0, (float) $workout->distance_meters);
        $this->assertSame(600, (int) $workout->active_calories);
        $this->assertSame(145, (int) $workout->average_heart_rate);

        // Rentang tanggal + stream diminta dalam SATU panggilan (hemat kuota Suunto).
        Http::assertSent(function ($request) {
            $url = $request->url();

            return str_contains($url, '/v3/workouts')
                && str_contains($url, 'extensions=')
                && $request->hasHeader('Ocp-Apim-Subscription-Key', 'test-key')
                && $request->hasHeader('Authorization', 'Bearer '.$this->accessToken('athlete@suunto'));
        });
    }

    public function test_sync_refreshes_an_expired_token_before_calling_the_api(): void
    {
        $user = $this->connectedUser(expiresAt: now()->subDay());

        Http::fake([
            'cloudapi-oauth.suunto.com/*' => Http::response($this->tokenSet('athlete@suunto', suffix: '2')),
            'cloudapi.suunto.com/v3/workouts*' => Http::response(['workouts' => []]),
        ]);

        $result = app(SuuntoSyncService::class)->syncForUser($user, 7);

        $this->assertSame('success', $result['status']);
        $this->assertSame($this->accessToken('athlete@suunto', '2'), $user->fresh()->suuntoConnection->access_token);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/oauth/token')
            && $request['grant_type'] === 'refresh_token'
            && $request['refresh_token'] === 'refresh-token-1');

        Http::assertSent(fn ($request) => str_contains($request->url(), '/v3/workouts')
            && $request->hasHeader('Authorization', 'Bearer '.$this->accessToken('athlete@suunto', '2')));
    }

    public function test_sync_records_a_readable_error_when_suunto_fails(): void
    {
        $user = $this->connectedUser();

        Http::fake([
            'cloudapi.suunto.com/v3/workouts*' => Http::response(['message' => 'Weekly quota exceeded'], 429),
        ]);

        $result = app(SuuntoSyncService::class)->syncForUser($user, 7);

        $this->assertSame('error', $result['status']);
        $this->assertStringContainsString('429', (string) $result['message']);
        $this->assertStringContainsString('Weekly quota exceeded', (string) $result['message']);

        $connection = $user->fresh()->suuntoConnection;
        $this->assertSame('error', $connection->last_sync_status);
        $this->assertNotNull($connection->last_synced_at);
    }

    public function test_sync_without_a_connection_reports_an_error(): void
    {
        $result = app(SuuntoSyncService::class)->syncForUser(User::factory()->create(), 7);

        $this->assertSame('error', $result['status']);
        $this->assertSame('Belum terhubung ke Suunto.', $result['message']);
    }

    public function test_disconnect_deletes_the_connection(): void
    {
        $user = $this->connectedUser();

        $this->actingAs($user)
            ->post(route('settings.suunto.disconnect'))
            ->assertRedirect(route('settings.suunto.show'));

        $this->assertNull($user->fresh()->suuntoConnection);
    }

    private function connectedUser($expiresAt = null): User
    {
        $user = User::factory()->create();

        SuuntoConnection::create([
            'user_id' => $user->id,
            'suunto_username' => 'athlete@suunto',
            'access_token' => $this->accessToken('athlete@suunto'),
            'refresh_token' => 'refresh-token-1',
            'expires_at' => $expiresAt ?? now()->addHours(12),
            'scope' => 'workout',
            'connected_at' => now(),
        ]);

        return $user->fresh();
    }

    /**
     * Access token Suunto berupa JWT; username diambil mapper dari klaim `user`.
     *
     * @return array<string, mixed>
     */
    private function tokenSet(string $username, string $suffix = '1'): array
    {
        return [
            'access_token' => $this->accessToken($username, $suffix),
            'refresh_token' => "refresh-token-{$suffix}",
            'expires_in' => 86400,
            'scope' => 'workout',
            'token_type' => 'bearer',
        ];
    }

    /** JWT tanpa tanda tangan (klaim saja) — cukup untuk menguji ekstraksi username. */
    private function accessToken(string $username, string $suffix = '1'): string
    {
        $encode = static fn (array $claims): string => rtrim(strtr(base64_encode(json_encode($claims)), '+/', '-_'), '=');

        return $encode(['alg' => 'HS256', 'typ' => 'JWT'])
            .'.'.$encode(['user' => $username, 'marker' => $suffix])
            .'.signature';
    }

    /**
     * Payload kira-kira mengikuti /v3/workouts (bentuk pasti belum bisa
     * diverifikasi tanpa akun berlangganan — mapper sengaja toleran).
     *
     * @return array<string, mixed>
     */
    private function workoutPayload(): array
    {
        return [
            'workoutKey' => 'workout-key-1',
            'startTime' => '2026-09-12T22:31:00.000Z',
            'totalTime' => 3600,
            'totalDistance' => 10000,
            'totalAscent' => 120,
            'totalDescent' => 110,
            'calories' => 600,
            'avgHeartRate' => 145,
            'maxHeartRate' => 172,
            'sport' => 'running',
            'name' => 'Morning Run',
        ];
    }
}
