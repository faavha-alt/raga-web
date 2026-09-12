<?php

namespace Tests\Feature;

use App\Models\Follow;
use App\Models\User;
use App\Models\Workout;
use App\Support\ActivityVisibility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AthleteProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_loads_and_shows_the_athletes_name(): void
    {
        $athlete = $this->user('budi', 'Budi Santoso');
        $viewer = $this->user('viewer');

        $this->actingAs($viewer)->get('/@budi')
            ->assertOk()
            ->assertSee('Budi Santoso')
            // Blade merender '@' sebagai entitas agar username seperti "if" tidak
            // dianggap direktif; teks yang terlihat tetap "@budi".
            ->assertSee('&#64;budi', false);
    }

    public function test_unknown_username_returns_404(): void
    {
        $viewer = $this->user('viewer');

        $this->actingAs($viewer)->get('/@tidak-ada')->assertNotFound();
    }

    public function test_user_without_username_is_not_reachable(): void
    {
        $viewer = $this->user('viewer');
        User::factory()->create(['username' => null]);

        $this->actingAs($viewer)->get('/@')->assertNotFound();
    }

    public function test_private_profile_shows_the_private_state_to_a_stranger(): void
    {
        $athlete = $this->user('rahasia', 'Atlet Privat');
        $athlete->update(['is_public' => false]);
        $this->workout($athlete, 'Aktivitas Privat Atlet', ActivityVisibility::Public);

        $stranger = $this->user('stranger');

        $this->actingAs($stranger)->get('/@rahasia')
            ->assertOk()
            ->assertSee('Atlet Privat')
            ->assertSee('Profil ini privat')
            ->assertDontSee('Aktivitas Privat Atlet');
    }

    public function test_private_profile_shows_activities_to_its_owner(): void
    {
        $athlete = $this->user('rahasia', 'Atlet Privat');
        $athlete->update(['is_public' => false]);
        $this->workout($athlete, 'Aktivitas Privat Atlet', ActivityVisibility::Public);

        $this->actingAs($athlete)->get('/@rahasia')
            ->assertOk()
            ->assertSee('Aktivitas Privat Atlet');
    }

    public function test_private_profile_shows_activities_to_a_follower(): void
    {
        $athlete = $this->user('rahasia', 'Atlet Privat');
        $athlete->update(['is_public' => false]);
        $this->workout($athlete, 'Aktivitas Privat Atlet', ActivityVisibility::Public);

        $follower = $this->user('follower');
        Follow::create(['follower_id' => $follower->id, 'following_id' => $athlete->id]);

        $this->actingAs($follower)->get('/@rahasia')
            ->assertOk()
            ->assertSee('Aktivitas Privat Atlet');
    }

    public function test_stats_only_count_activities_the_viewer_may_see(): void
    {
        $athlete = $this->user('budi', 'Budi Santoso');
        $this->workout($athlete, 'Publik 5K', ActivityVisibility::Public, km: 5);
        $this->workout($athlete, 'Privat 10K', ActivityVisibility::Private, km: 10);

        $stranger = $this->user('stranger');

        $this->actingAs($stranger)->get('/@budi')
            ->assertOk()
            ->assertViewHas('stats', fn (array $stats) => $stats['total_activities'] === 1
                && $stats['total_distance_meters'] === 5000.0);
    }

    public function test_own_profile_shows_edit_link_instead_of_follow_button(): void
    {
        $athlete = $this->user('budi', 'Budi Santoso');

        $this->actingAs($athlete)->get('/@budi')
            ->assertOk()
            ->assertSee('Edit Profil')
            ->assertDontSee('Ikuti');
    }

    public function test_followers_page_lists_followers(): void
    {
        $athlete = $this->user('budi', 'Budi Santoso');
        $follower = $this->user('siti', 'Siti Aminah');
        Follow::create(['follower_id' => $follower->id, 'following_id' => $athlete->id]);

        $this->actingAs($athlete)->get(route('athletes.followers', $athlete))
            ->assertOk()
            ->assertSee('Siti Aminah');
    }

    public function test_profile_never_renders_health_data_strings(): void
    {
        $athlete = $this->user('budi', 'Budi Santoso');
        $this->workout($athlete, 'Lari Sore', ActivityVisibility::Public);

        $viewer = $this->user('viewer');

        $this->actingAs($viewer)->get('/@budi')
            ->assertOk()
            ->assertDontSee('HRV')
            ->assertDontSee('readiness')
            ->assertDontSee('Body Battery');
    }

    private function user(string $username, ?string $name = null): User
    {
        return User::factory()->create([
            'name' => $name ?? ucfirst($username).' Test',
            'username' => $username,
        ]);
    }

    private function workout(User $user, string $name, ActivityVisibility $visibility, float $km = 10): Workout
    {
        $start = Carbon::parse('2026-09-01 06:00:00');

        return Workout::create([
            'user_id' => $user->id,
            'type' => 'running',
            'name' => $name,
            'start_date' => $start,
            'end_date' => $start->copy()->addHour(),
            'distance_meters' => $km * 1000,
            'source' => 'garmin',
            'visibility' => $visibility->value,
        ]);
    }
}
