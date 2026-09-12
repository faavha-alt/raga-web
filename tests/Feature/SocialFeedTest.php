<?php

namespace Tests\Feature;

use App\Models\Follow;
use App\Models\Kudos;
use App\Models\User;
use App\Models\Workout;
use App\Support\ActivityVisibility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class SocialFeedTest extends TestCase
{
    use RefreshDatabase;

    public function test_feed_requires_login(): void
    {
        $this->get('/feed')->assertRedirect(route('login'));
    }

    public function test_feed_shows_a_followed_users_public_activity(): void
    {
        $viewer = $this->user('viewer');
        $friend = $this->user('friend');
        $workout = $this->workout($friend, 'Lari Pagi Budi', ActivityVisibility::Public);

        Follow::create(['follower_id' => $viewer->id, 'following_id' => $friend->id]);

        $this->actingAs($viewer)->get('/feed')
            ->assertOk()
            ->assertSee('Lari Pagi Budi')
            ->assertSee($friend->name);
    }

    public function test_feed_does_not_show_a_strangers_private_activity(): void
    {
        $viewer = $this->user('viewer');
        $stranger = $this->user('stranger');
        $this->workout($stranger, 'Rahasia Stranger', ActivityVisibility::Private);

        $this->actingAs($viewer)->get('/feed')
            ->assertOk()
            ->assertDontSee('Rahasia Stranger');
    }

    public function test_feed_does_not_show_a_followers_activity_from_a_non_followed_user(): void
    {
        $viewer = $this->user('viewer');
        $stranger = $this->user('stranger');
        $this->workout($stranger, 'Hanya Untuk Pengikut', ActivityVisibility::Followers);

        $this->actingAs($viewer)->get('/feed')
            ->assertOk()
            ->assertDontSee('Hanya Untuk Pengikut');
    }

    public function test_feed_shows_the_viewers_own_private_activity(): void
    {
        $viewer = $this->user('viewer');
        $this->workout($viewer, 'Latihan Privat Sendiri', ActivityVisibility::Private);

        $this->actingAs($viewer)->get('/feed')
            ->assertOk()
            ->assertSee('Latihan Privat Sendiri');
    }

    public function test_feed_shows_a_followed_users_followers_only_activity(): void
    {
        $viewer = $this->user('viewer');
        $friend = $this->user('friend');
        $this->workout($friend, 'Sesi Pengikut Saja', ActivityVisibility::Followers);

        Follow::create(['follower_id' => $viewer->id, 'following_id' => $friend->id]);

        $this->actingAs($viewer)->get('/feed')
            ->assertOk()
            ->assertSee('Sesi Pengikut Saja');
    }

    public function test_feed_paginates_twenty_per_page(): void
    {
        $viewer = $this->user('viewer');

        for ($i = 0; $i < 25; $i++) {
            $this->workout($viewer, 'Aktivitas '.$i, ActivityVisibility::Private, Carbon::parse('2026-09-01')->addDays($i));
        }

        $this->actingAs($viewer)->get('/feed')
            ->assertOk()
            ->assertViewHas('activities', fn ($paginator) => $paginator->perPage() === 20 && $paginator->total() === 25);
    }

    public function test_feed_does_not_show_a_followed_users_private_activity(): void
    {
        $viewer = $this->user('viewer');
        $friend = $this->user('friend');
        $this->workout($friend, 'Privat Milik Teman', ActivityVisibility::Private);

        Follow::create(['follower_id' => $viewer->id, 'following_id' => $friend->id]);

        $this->actingAs($viewer)->get('/feed')
            ->assertOk()
            ->assertDontSee('Privat Milik Teman');
    }

    public function test_feed_marks_kudos_the_viewer_has_given(): void
    {
        $viewer = $this->user('viewer');
        $friend = $this->user('friend');
        $workout = $this->workout($friend, 'Lari Bersama', ActivityVisibility::Public);
        Kudos::create(['user_id' => $viewer->id, 'workout_id' => $workout->id]);

        Follow::create(['follower_id' => $viewer->id, 'following_id' => $friend->id]);

        $this->actingAs($viewer)->get('/feed')
            ->assertOk()
            ->assertViewHas('activities', fn ($paginator) => (bool) $paginator->first()->viewer_has_kudos === true);
    }

    public function test_explore_page_loads_and_suggests_an_unfollowed_athlete(): void
    {
        $viewer = $this->user('viewer');
        $this->user('siti', 'Siti Aminah');
        $this->workout($this->user('siti2', 'Siti Lain'), 'Aktivitas Publik Siti', ActivityVisibility::Public);

        $this->actingAs($viewer)->get('/explore')
            ->assertOk()
            ->assertSee('Siti Aminah')
            ->assertSee('Aktivitas Publik Siti');
    }

    private function user(string $username, ?string $name = null): User
    {
        return User::factory()->create([
            'name' => $name ?? ucfirst($username).' Test',
            'username' => $username,
        ]);
    }

    private function workout(User $user, string $name, ActivityVisibility $visibility, ?Carbon $start = null): Workout
    {
        $start ??= Carbon::parse('2026-09-01 06:00:00');

        return Workout::create([
            'user_id' => $user->id,
            'type' => 'running',
            'name' => $name,
            'start_date' => $start,
            'end_date' => $start->copy()->addHour(),
            'distance_meters' => 10000,
            'source' => 'garmin',
            'visibility' => $visibility->value,
        ]);
    }
}
