<?php

namespace Tests\Feature;

use App\Models\Follow;
use App\Models\User;
use App\Models\Workout;
use App\Support\ActivityVisibility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Gerbang privasi aktivitas.
 *
 * Ini permukaan paling berbahaya dari fitur sosial: aktivitas berisi lokasi
 * rumah pengguna. Test ini mengunci aturan sebelum ada UI yang memakainya.
 */
class ActivityVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private function workout(User $user, ActivityVisibility $visibility): Workout
    {
        $start = Carbon::parse('2026-09-01 06:00:00');

        return $user->workouts()->create([
            'type' => 'running',
            'start_date' => $start,
            'end_date' => $start->copy()->addHour(),
            'distance_meters' => 10000,
            'source' => 'garmin',
            'visibility' => $visibility->value,
        ]);
    }

    public function test_default_visibility_is_private(): void
    {
        $user = User::factory()->create();
        $start = Carbon::parse('2026-09-01 06:00:00');

        // Tanpa menyebut visibility sama sekali, aktivitas tidak boleh publik.
        $workout = $user->workouts()->create([
            'type' => 'running',
            'start_date' => $start,
            'end_date' => $start->copy()->addHour(),
            'distance_meters' => 5000,
            'source' => 'garmin',
        ]);

        $this->assertSame(ActivityVisibility::Private, $workout->fresh()->visibility);
        $this->assertDatabaseHas('workouts', ['id' => $workout->id, 'visibility' => 'private']);
    }

    public function test_public_activity_is_visible_to_guest(): void
    {
        $owner = User::factory()->create();
        $workout = $this->workout($owner, ActivityVisibility::Public);

        $this->assertTrue($workout->isVisibleTo(null));
    }

    public function test_private_activity_is_hidden_from_guest(): void
    {
        $owner = User::factory()->create();
        $workout = $this->workout($owner, ActivityVisibility::Private);

        $this->assertFalse($workout->isVisibleTo(null));
    }

    public function test_private_activity_is_hidden_from_other_user(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $workout = $this->workout($owner, ActivityVisibility::Private);

        $this->assertFalse($workout->isVisibleTo($stranger));
    }

    public function test_owner_always_sees_own_private_activity(): void
    {
        $owner = User::factory()->create();
        $workout = $this->workout($owner, ActivityVisibility::Private);

        $this->assertTrue($workout->isVisibleTo($owner));
    }

    public function test_followers_only_activity_is_visible_to_follower_but_not_stranger(): void
    {
        $owner = User::factory()->create();
        $follower = User::factory()->create();
        $stranger = User::factory()->create();

        Follow::create(['follower_id' => $follower->id, 'following_id' => $owner->id]);

        $workout = $this->workout($owner, ActivityVisibility::Followers);

        $this->assertTrue($workout->isVisibleTo($follower));
        $this->assertFalse($workout->isVisibleTo($stranger));
    }

    public function test_scope_visible_to_guest_only_returns_public(): void
    {
        $owner = User::factory()->create();
        $public = $this->workout($owner, ActivityVisibility::Public);
        $this->workout($owner, ActivityVisibility::Followers);
        $this->workout($owner, ActivityVisibility::Private);

        $ids = Workout::query()->visibleTo(null)->pluck('id')->all();

        $this->assertSame([$public->id], $ids);
    }

    public function test_scope_visible_to_follower_returns_public_own_and_followed_followers_only(): void
    {
        $owner = User::factory()->create();
        $follower = User::factory()->create();
        $stranger = User::factory()->create();

        Follow::create(['follower_id' => $follower->id, 'following_id' => $owner->id]);

        $ownerPublic = $this->workout($owner, ActivityVisibility::Public);
        $ownerFollowers = $this->workout($owner, ActivityVisibility::Followers);
        $ownerPrivate = $this->workout($owner, ActivityVisibility::Private);
        $followerOwnPrivate = $this->workout($follower, ActivityVisibility::Private);
        $strangerPublic = $this->workout($stranger, ActivityVisibility::Public);
        $strangerFollowers = $this->workout($stranger, ActivityVisibility::Followers);

        $ids = Workout::query()->visibleTo($follower)->pluck('id')->all();
        sort($ids);

        $expected = [$ownerPublic->id, $ownerFollowers->id, $followerOwnPrivate->id, $strangerPublic->id];
        sort($expected);

        $this->assertSame($expected, $ids);
        $this->assertNotContains($ownerPrivate->id, $ids);
        $this->assertNotContains($strangerFollowers->id, $ids);
    }

    public function test_scope_visible_to_owner_returns_all_own_activities_plus_public(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $ownPrivate = $this->workout($owner, ActivityVisibility::Private);
        $ownFollowers = $this->workout($owner, ActivityVisibility::Followers);
        $otherPrivate = $this->workout($other, ActivityVisibility::Private);

        $ids = Workout::query()->visibleTo($owner)->pluck('id')->all();

        $this->assertContains($ownPrivate->id, $ids);
        $this->assertContains($ownFollowers->id, $ids);
        $this->assertNotContains($otherPrivate->id, $ids);
    }

    public function test_visibility_cast_round_trips_through_database(): void
    {
        $owner = User::factory()->create();
        $workout = $this->workout($owner, ActivityVisibility::Followers);

        $this->assertSame(ActivityVisibility::Followers, $workout->fresh()->visibility);
    }
}
