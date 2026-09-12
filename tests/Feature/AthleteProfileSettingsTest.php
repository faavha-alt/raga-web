<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\AvatarStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Registrasi publik & pengaturan profil atlet.
 */
class AthleteProfileSettingsTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<int, string> */
    private array $avatarsToCleanUp = [];

    protected function tearDown(): void
    {
        foreach ($this->avatarsToCleanUp as $path) {
            $full = public_path($path);
            if (is_file($full)) {
                unlink($full);
            }
        }

        $this->avatarsToCleanUp = [];

        parent::tearDown();
    }

    private function assertAvatarFileExistsAndTrack(string $path): void
    {
        $full = public_path($path);

        $this->assertTrue(is_file($full), "Avatar tidak ditemukan di {$path}");
        $this->avatarsToCleanUp[] = $path;
    }

    public function test_registration_requires_a_username(): void
    {
        $response = $this->post('/register', [
            'name' => 'Atlet Baru',
            'email' => 'atlet@example.test',
            'password' => 'password-baik-123',
            'password_confirmation' => 'password-baik-123',
        ]);

        $response->assertSessionHasErrors('username');
        $this->assertSame(0, User::count());
    }

    public function test_registration_creates_user_with_username(): void
    {
        $response = $this->post('/register', [
            'name' => 'Atlet Baru',
            'username' => 'atlet.baru',
            'email' => 'atlet@example.test',
            'password' => 'password-baik-123',
            'password_confirmation' => 'password-baik-123',
        ]);

        $response->assertRedirect(route('dashboard', absolute: false));

        $user = User::sole();
        $this->assertSame('atlet.baru', $user->username);
        $this->assertAuthenticatedAs($user);
    }

    public function test_registration_normalises_uppercase_username_to_lowercase(): void
    {
        $this->post('/register', [
            'name' => 'Atlet Baru',
            'username' => '  AtletKeren  ',
            'email' => 'atlet@example.test',
            'password' => 'password-baik-123',
            'password_confirmation' => 'password-baik-123',
        ])->assertRedirect(route('dashboard', absolute: false));

        $this->assertSame('atletkeren', User::sole()->username);
    }

    public function test_registration_rejects_duplicate_username(): void
    {
        User::factory()->create(['username' => 'sudahdipakai']);

        $this->post('/register', [
            'name' => 'Atlet Baru',
            'username' => 'sudahdipakai',
            'email' => 'atlet@example.test',
            'password' => 'password-baik-123',
            'password_confirmation' => 'password-baik-123',
        ])->assertSessionHasErrors('username');

        $this->assertSame(1, User::count());
    }

    public function test_registration_rejects_invalid_username_characters(): void
    {
        $this->post('/register', [
            'name' => 'Atlet Baru',
            'username' => 'atlet keren!',
            'email' => 'atlet@example.test',
            'password' => 'password-baik-123',
            'password_confirmation' => 'password-baik-123',
        ])->assertSessionHasErrors('username');

        $this->assertSame(0, User::count());
    }

    public function test_profile_update_saves_social_fields(): void
    {
        $user = User::factory()->create(['username' => 'lama']);

        $this->actingAs($user)->patch('/profile', [
            'name' => $user->name,
            'username' => 'baru',
            'email' => $user->email,
            'bio' => 'Pencinta lari pagi.',
            'location' => 'Surakarta',
        ])->assertRedirect(route('profile.edit'));

        $user->refresh();
        $this->assertSame('baru', $user->username);
        $this->assertSame('Pencinta lari pagi.', $user->bio);
        $this->assertSame('Surakarta', $user->location);
    }

    public function test_profile_update_rejects_username_taken_by_another_user(): void
    {
        $user = User::factory()->create(['username' => 'punyaku']);
        User::factory()->create(['username' => 'punyaorang']);

        $this->actingAs($user)->patch('/profile', [
            'name' => $user->name,
            'username' => 'punyaorang',
            'email' => $user->email,
        ])->assertSessionHasErrors('username');

        $this->assertSame('punyaku', $user->refresh()->username);
    }

    public function test_profile_update_keeps_own_username(): void
    {
        $user = User::factory()->create(['username' => 'punyaku']);

        $this->actingAs($user)->patch('/profile', [
            'name' => 'Nama Diperbarui',
            'username' => 'punyaku',
            'email' => $user->email,
        ])->assertRedirect(route('profile.edit'));

        $this->assertSame('Nama Diperbarui', $user->refresh()->name);
    }

    public function test_unchecked_public_checkbox_makes_profile_private(): void
    {
        $user = User::factory()->create(['username' => 'aku', 'is_public' => true]);

        // Checkbox yang tidak dicentang tidak mengirim field sama sekali.
        $this->actingAs($user)->patch('/profile', [
            'name' => $user->name,
            'username' => $user->username,
            'email' => $user->email,
        ])->assertRedirect(route('profile.edit'));

        $this->assertFalse($user->refresh()->is_public);
    }

    public function test_checked_public_checkbox_makes_profile_public(): void
    {
        $user = User::factory()->create(['username' => 'aku', 'is_public' => false]);

        $this->actingAs($user)->patch('/profile', [
            'name' => $user->name,
            'username' => $user->username,
            'email' => $user->email,
            'is_public' => '1',
        ])->assertRedirect(route('profile.edit'));

        $this->assertTrue($user->refresh()->is_public);
    }

    public function test_avatar_upload_stores_file_and_records_path(): void
    {
        $user = User::factory()->create(['username' => 'aku']);

        $this->actingAs($user)->patch('/profile', [
            'name' => $user->name,
            'username' => $user->username,
            'email' => $user->email,
            'avatar' => UploadedFile::fake()->image('foto.jpg', 200, 200),
        ])->assertRedirect(route('profile.edit'));

        $path = $user->refresh()->avatar_path;

        $this->assertNotNull($path);
        $this->assertStringStartsWith(AvatarStorage::DIRECTORY.'/', $path);
        $this->assertAvatarFileExistsAndTrack($path);
    }

    public function test_avatar_upload_rejects_non_image(): void
    {
        $user = User::factory()->create(['username' => 'aku']);

        $this->actingAs($user)->patch('/profile', [
            'name' => $user->name,
            'username' => $user->username,
            'email' => $user->email,
            'avatar' => UploadedFile::fake()->create('dokumen.pdf', 10, 'application/pdf'),
        ])->assertSessionHasErrors('avatar');

        $this->assertNull($user->refresh()->avatar_path);
    }

    public function test_backfill_command_assigns_username_to_users_without_one(): void
    {
        $user = User::factory()->create(['name' => 'Budi Santoso', 'username' => null]);

        $this->artisan('users:backfill-usernames')->assertSuccessful();

        $this->assertSame('budisantoso', $user->refresh()->username);
    }

    public function test_profile_edit_page_loads(): void
    {
        $user = User::factory()->create(['username' => 'aku']);

        $this->actingAs($user)->get('/profile')->assertOk()->assertSee('Profil Atlet');
    }

    public function test_avatar_storage_directory_is_ignored_by_git(): void
    {
        // Berkas unggahan tidak boleh ikut ter-commit.
        $gitignore = File::get(base_path('.gitignore'));

        $this->assertStringContainsString('uploads', $gitignore);
    }
}
