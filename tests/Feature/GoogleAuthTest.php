<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Laravel\Socialite\Two\User as SocialiteUser;
use Tests\TestCase;

/**
 * Masuk dan daftar lewat Google.
 *
 * Alur OAuth-nya sendiri disimulasikan lewat Socialite::fake(); yang diuji di
 * sini adalah keputusan aplikasi setelah identitas Google diterima — siapa yang
 * dibuatkan akun, siapa yang ditautkan, dan permintaan seperti apa yang ditolak.
 *
 * Satu test (redirect) sengaja tidak memakai fake karena tujuan utamanya adalah
 * memastikan URL otorisasi Google benar-benar terbentuk dari konfigurasi.
 */
class GoogleAuthTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 1x1 piksel PNG yang sah, cukup untuk lolos pemeriksaan isi berkas.
     */
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8DwHwAFAAH/q842iQAAAABJRU5ErkJggg==';

    /**
     * Berkas avatar yang dibuat selama test, untuk dibersihkan setelah selesai.
     *
     * @var list<string>
     */
    private array $avatarFiles = [];

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.google.client_id', 'test-client-id');
        config()->set('services.google.client_secret', 'test-client-secret');
    }

    protected function tearDown(): void
    {
        foreach ($this->avatarFiles as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        $this->avatarFiles = [];

        parent::tearDown();
    }

    /**
     * Identitas Google tiruan. `avatar` null secara default supaya test yang
     * tidak peduli foto profil tidak memicu unduhan HTTP.
     */
    private function googleUser(array $attributes = []): SocialiteUser
    {
        return SocialiteUser::fake(array_merge([
            'id' => '100200300',
            'name' => 'Zulfa Nurulhakim',
            'email' => 'zulfa@example.com',
            'verified_email' => true,
            'avatar' => null,
        ], $attributes));
    }

    private function hitCallback(array $query = [])
    {
        return $this->get(route('auth.google.callback', $query));
    }

    /**
     * Pastikan avatar benar-benar ditulis ke disk, lalu catat untuk dibersihkan.
     */
    private function assertAvatarStored(User $user): void
    {
        $this->assertNotNull($user->avatar_path);
        $this->assertStringStartsWith('uploads/avatars/', $user->avatar_path);

        $full = public_path($user->avatar_path);

        $this->assertFileExists($full);

        $this->avatarFiles[] = $full;
    }

    public function test_login_page_offers_google_when_credentials_are_configured(): void
    {
        $response = $this->get('/login');

        $response->assertOk();
        $response->assertSee('Lanjutkan dengan Google');
        $response->assertSee(route('auth.google.redirect'), escape: false);
    }

    public function test_register_page_offers_google_when_credentials_are_configured(): void
    {
        $response = $this->get('/register');

        $response->assertOk();
        $response->assertSee('Daftar dengan Google');
        $response->assertSee(route('auth.google.redirect'), escape: false);
    }

    public function test_google_button_is_hidden_while_credentials_are_missing(): void
    {
        config()->set('services.google.client_id', null);

        // Tanpa client ID, tombolnya hanya akan menabrak halaman error Google.
        $this->get('/login')->assertOk()->assertDontSee('/auth/google/redirect');
        $this->get('/register')->assertOk()->assertDontSee('/auth/google/redirect');
    }

    public function test_redirect_route_builds_a_google_authorization_url(): void
    {
        // Tanpa Socialite::fake(): rute ini harus benar-benar membentuk URL dari
        // konfigurasi, bukan dari driver tiruan.
        $response = $this->get(route('auth.google.redirect'));

        $response->assertRedirectContains('https://accounts.google.com');
        $response->assertRedirectContains('client_id=test-client-id');
        $response->assertRedirectContains('response_type=code');
        $response->assertRedirectContains('state=');

        // Socialite menyimpan `state` di sesi untuk memvalidasi callback.
        $this->assertNotNull(session('state'));
    }

    public function test_callback_creates_an_account_for_an_unknown_google_identity(): void
    {
        Socialite::fake('google', $this->googleUser());

        $this->hitCallback()->assertRedirect('/dashboard');

        $user = User::query()->sole();

        $this->assertAuthenticatedAs($user);
        $this->assertSame('zulfa@example.com', $user->email);
        $this->assertSame('Zulfa Nurulhakim', $user->name);
        $this->assertSame('100200300', $user->google_id);

        // Username diambil dari nama dan harus lolos aturan form registrasi.
        $this->assertSame('zulfanurulhakim', $user->username);

        // Akun dari Google memang tidak punya password; halaman ganti password
        // memakai kolom NULL ini untuk melewati verifikasi current_password.
        $this->assertNull($user->password);
        $this->assertFalse($user->hasPassword());

        // Google menyatakan emailnya terverifikasi.
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_callback_generates_a_unique_username_when_the_name_is_taken(): void
    {
        User::factory()->create(['username' => 'zulfanurulhakim']);

        Socialite::fake('google', $this->googleUser());

        $this->hitCallback()->assertRedirect('/dashboard');

        $created = User::query()->whereNotNull('google_id')->sole();

        $this->assertSame('zulfanurulhakim2', $created->username);
    }

    public function test_username_falls_back_to_the_email_local_part_when_the_name_has_no_latin_letters(): void
    {
        Socialite::fake('google', $this->googleUser([
            'name' => '走る人',
            'email' => 'pelari.cepat@example.com',
        ]));

        $this->hitCallback()->assertRedirect('/dashboard');

        $this->assertSame('pelari.cepat', User::query()->sole()->username);
    }

    public function test_callback_signs_in_an_existing_google_account_without_creating_a_duplicate(): void
    {
        $existing = User::factory()->create(['google_id' => '100200300']);
        $originalEmail = $existing->email;

        Socialite::fake('google', $this->googleUser(['email' => 'beda@example.com']));

        $this->hitCallback()->assertRedirect('/dashboard');

        // `sub` yang sama berarti orang yang sama, walau email di Google berbeda
        // dari yang tersimpan. Email lokal sengaja tidak ditimpa: alamat itu
        // dipakai untuk login dan reset password, dan menimpanya bisa menabrak
        // kolom unik justru di tengah proses masuk.
        $this->assertSame(1, User::query()->count());
        $this->assertAuthenticatedAs($existing);
        $this->assertSame($originalEmail, $existing->refresh()->email);
    }

    public function test_callback_links_google_to_an_existing_password_account(): void
    {
        $existing = User::factory()->unverified()->create([
            'name' => 'Nama Pilihan Sendiri',
            'email' => 'zulfa@example.com',
        ]);

        Socialite::fake('google', $this->googleUser(['name' => 'Nama Dari Google']));

        $this->hitCallback()->assertRedirect('/dashboard');

        $existing->refresh();

        $this->assertSame(1, User::query()->count());
        $this->assertAuthenticatedAs($existing);
        $this->assertSame('100200300', $existing->google_id);

        // Password lama tidak boleh tersentuh oleh penautan.
        $this->assertTrue(Hash::check('password', $existing->password));
        $this->assertTrue($existing->hasPassword());

        // Nama yang sudah dipilih pengguna tidak ditimpa oleh nama dari Google.
        $this->assertSame('Nama Pilihan Sendiri', $existing->name);

        // Google sudah memverifikasi emailnya, jadi statusnya ikut terangkat.
        $this->assertNotNull($existing->email_verified_at);
    }

    public function test_callback_refuses_to_link_when_google_has_not_verified_the_email(): void
    {
        $existing = User::factory()->create(['email' => 'zulfa@example.com']);

        Socialite::fake('google', $this->googleUser(['verified_email' => false]));

        $this->hitCallback()->assertRedirect('/login')->assertSessionHasErrors('google');

        // Menautkan di sini berarti membuka pengambilalihan akun lewat pemilik
        // alamat email yang belum tentu orang yang sedang login.
        $this->assertNull($existing->refresh()->google_id);
        $this->assertGuest();
    }

    public function test_callback_refuses_when_google_shares_no_email(): void
    {
        Socialite::fake('google', $this->googleUser(['email' => null]));

        $this->hitCallback()->assertRedirect('/login')->assertSessionHasErrors('google');

        $this->assertSame(0, User::query()->count());
        $this->assertGuest();
    }

    public function test_callback_reports_an_expired_session_instead_of_failing(): void
    {
        Socialite::fake('google', fn () => throw new InvalidStateException);

        $this->hitCallback()->assertRedirect('/login')->assertSessionHasErrors('google');

        $this->assertGuest();
    }

    public function test_callback_handles_a_cancelled_consent_screen(): void
    {
        $this->hitCallback(['error' => 'access_denied'])
            ->assertRedirect('/login')
            ->assertSessionHasErrors('google');

        $this->assertGuest();
    }

    public function test_avatar_is_downloaded_into_local_storage(): void
    {
        Http::fake([
            'lh3.googleusercontent.com/*' => Http::response(
                base64_decode(self::PNG),
                200,
                ['Content-Type' => 'image/png'],
            ),
        ]);

        Socialite::fake('google', $this->googleUser([
            'avatar' => 'https://lh3.googleusercontent.com/a/abc=s96-c',
        ]));

        $this->hitCallback()->assertRedirect('/dashboard');

        $user = User::query()->sole();

        $this->assertAvatarStored($user);

        // Foto disimpan sendiri, bukan di-hotlink, dan diminta dalam ukuran yang
        // cukup tajam untuk layar beretina.
        Http::assertSent(fn ($request) => $request->url() === 'https://lh3.googleusercontent.com/a/abc=s256-c');

        $this->assertStringStartsWith(asset('uploads/avatars/'), (string) $user->avatarUrl());
    }

    public function test_avatar_download_failure_does_not_block_registration(): void
    {
        Http::fake([
            'lh3.googleusercontent.com/*' => Http::response('', 500),
        ]);

        Socialite::fake('google', $this->googleUser([
            'avatar' => 'https://lh3.googleusercontent.com/a/abc',
        ]));

        $this->hitCallback()->assertRedirect('/dashboard');

        $user = User::query()->sole();

        $this->assertAuthenticatedAs($user);
        $this->assertNull($user->avatar_path);
        $this->assertNull($user->avatarUrl());
    }

    public function test_non_image_avatar_response_is_not_stored(): void
    {
        Http::fake([
            'lh3.googleusercontent.com/*' => Http::response(
                '<html>bukan gambar</html>',
                200,
                ['Content-Type' => 'image/png'],
            ),
        ]);

        Socialite::fake('google', $this->googleUser([
            'avatar' => 'https://lh3.googleusercontent.com/a/abc',
        ]));

        $this->hitCallback()->assertRedirect('/dashboard');

        // Header boleh diklaim sebagai PNG; yang dipercaya adalah isi berkasnya.
        $this->assertNull(User::query()->sole()->avatar_path);
    }

    public function test_google_user_can_set_a_password_without_confirming_the_current_one(): void
    {
        $user = User::factory()->create(['password' => null, 'google_id' => '100200300']);

        $this->actingAs($user)
            ->put(route('password.update'), [
                'password' => 'password-baru-123',
                'password_confirmation' => 'password-baru-123',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('password-updated', session('status'));
        $this->assertTrue(Hash::check('password-baru-123', $user->refresh()->password));
    }

    public function test_google_user_can_delete_their_account(): void
    {
        $user = User::factory()->create(['password' => null, 'google_id' => '100200300']);

        $this->actingAs($user)
            ->delete(route('profile.destroy'))
            ->assertRedirect('/');

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertGuest();
    }

    public function test_password_accounts_still_have_to_confirm_before_changing_or_deleting(): void
    {
        $user = User::factory()->create();

        // Ganti password tanpa menyebut password lama harus tetap ditolak.
        $this->actingAs($user)
            ->put(route('password.update'), [
                'password' => 'password-baru-123',
                'password_confirmation' => 'password-baru-123',
            ])
            ->assertSessionHasErrorsIn('updatePassword', 'current_password');

        // Begitu juga penghapusan akun.
        $this->actingAs($user)
            ->delete(route('profile.destroy'))
            ->assertSessionHasErrorsIn('userDeletion', 'password');

        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }
}
