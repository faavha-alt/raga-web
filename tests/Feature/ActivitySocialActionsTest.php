<?php

namespace Tests\Feature;

use App\Models\Comment;
use App\Models\Follow;
use App\Models\Kudos;
use App\Models\User;
use App\Models\Workout;
use App\Models\WorkoutLap;
use App\Models\WorkoutSample;
use App\Notifications\NewComment;
use App\Notifications\NewFollower;
use App\Notifications\NewKudos;
use App\Support\ActivityVisibility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ActivitySocialActionsTest extends TestCase
{
    use RefreshDatabase;

    // ---------------------------------------------------------------------
    // Kudos
    // ---------------------------------------------------------------------

    public function test_kudos_persists(): void
    {
        $owner = $this->user('owner');
        $viewer = $this->user('viewer');
        $workout = $this->workout($owner, ActivityVisibility::Public);

        $this->actingAs($viewer)->from(route('feed'))
            ->post(route('kudos.store', $workout))
            ->assertRedirect(route('feed'));

        $this->assertDatabaseHas('kudos', ['user_id' => $viewer->id, 'workout_id' => $workout->id]);
    }

    public function test_a_second_kudos_does_not_duplicate(): void
    {
        $owner = $this->user('owner');
        $viewer = $this->user('viewer');
        $workout = $this->workout($owner, ActivityVisibility::Public);

        $this->actingAs($viewer)->post(route('kudos.store', $workout));
        $this->actingAs($viewer)->post(route('kudos.store', $workout))->assertRedirect();

        $this->assertSame(1, Kudos::count());
    }

    public function test_removing_a_kudos_deletes_it(): void
    {
        $owner = $this->user('owner');
        $viewer = $this->user('viewer');
        $workout = $this->workout($owner, ActivityVisibility::Public);
        Kudos::create(['user_id' => $viewer->id, 'workout_id' => $workout->id]);

        $this->actingAs($viewer)->from(route('feed'))
            ->delete(route('kudos.destroy', $workout))
            ->assertRedirect(route('feed'));

        $this->assertDatabaseMissing('kudos', ['user_id' => $viewer->id, 'workout_id' => $workout->id]);
    }

    public function test_kudos_on_an_invisible_activity_returns_404(): void
    {
        $owner = $this->user('owner');
        $viewer = $this->user('viewer');
        $workout = $this->workout($owner, ActivityVisibility::Private);

        $this->actingAs($viewer)->post(route('kudos.store', $workout))->assertNotFound();

        $this->assertSame(0, Kudos::count());
    }

    public function test_new_kudos_notifies_the_activity_owner(): void
    {
        Notification::fake();

        $owner = $this->user('owner');
        $viewer = $this->user('viewer');
        $workout = $this->workout($owner, ActivityVisibility::Public);

        $this->actingAs($viewer)->post(route('kudos.store', $workout));

        Notification::assertSentTo($owner, NewKudos::class);
    }

    public function test_kudos_on_own_activity_does_not_notify(): void
    {
        Notification::fake();

        $owner = $this->user('owner');
        $workout = $this->workout($owner, ActivityVisibility::Public);

        $this->actingAs($owner)->post(route('kudos.store', $workout));

        Notification::assertNothingSent();
    }

    // ---------------------------------------------------------------------
    // Komentar
    // ---------------------------------------------------------------------

    public function test_comment_persists_with_the_right_body(): void
    {
        $owner = $this->user('owner');
        $viewer = $this->user('viewer');
        $workout = $this->workout($owner, ActivityVisibility::Public);

        $this->actingAs($viewer)->from(route('activities.show', $workout))
            ->post(route('comments.store', $workout), ['body' => 'Mantap, lanjutkan!'])
            ->assertRedirect(route('activities.show', $workout));

        $this->assertDatabaseHas('comments', [
            'user_id' => $viewer->id,
            'workout_id' => $workout->id,
            'body' => 'Mantap, lanjutkan!',
        ]);
    }

    public function test_comment_requires_a_body(): void
    {
        $owner = $this->user('owner');
        $viewer = $this->user('viewer');
        $workout = $this->workout($owner, ActivityVisibility::Public);

        $this->actingAs($viewer)
            ->post(route('comments.store', $workout), ['body' => ''])
            ->assertSessionHasErrors('body');

        $this->assertSame(0, Comment::count());
    }

    public function test_the_comment_author_can_delete_their_comment(): void
    {
        $owner = $this->user('owner');
        $author = $this->user('author');
        $workout = $this->workout($owner, ActivityVisibility::Public);
        $comment = Comment::create(['user_id' => $author->id, 'workout_id' => $workout->id, 'body' => 'Hapus saya']);
        $id = $comment->id;

        $this->actingAs($author)->delete(route('comments.destroy', $comment))->assertRedirect();

        $this->assertDatabaseMissing('comments', ['id' => $id]);
    }

    public function test_a_third_party_cannot_delete_a_comment(): void
    {
        $owner = $this->user('owner');
        $author = $this->user('author');
        $stranger = $this->user('stranger');
        $workout = $this->workout($owner, ActivityVisibility::Public);
        $comment = Comment::create(['user_id' => $author->id, 'workout_id' => $workout->id, 'body' => 'Jangan dihapus']);

        $this->actingAs($stranger)->delete(route('comments.destroy', $comment))->assertForbidden();

        $this->assertDatabaseHas('comments', ['id' => $comment->id]);
    }

    public function test_the_activity_owner_can_delete_a_comment(): void
    {
        $owner = $this->user('owner');
        $author = $this->user('author');
        $workout = $this->workout($owner, ActivityVisibility::Public);
        $comment = Comment::create(['user_id' => $author->id, 'workout_id' => $workout->id, 'body' => 'Dihapus pemilik']);
        $id = $comment->id;

        $this->actingAs($owner)->delete(route('comments.destroy', $comment))->assertRedirect();

        $this->assertDatabaseMissing('comments', ['id' => $id]);
    }

    public function test_comment_on_an_invisible_activity_returns_404(): void
    {
        $owner = $this->user('owner');
        $viewer = $this->user('viewer');
        $workout = $this->workout($owner, ActivityVisibility::Private);

        $this->actingAs($viewer)
            ->post(route('comments.store', $workout), ['body' => 'Coba tembus'])
            ->assertNotFound();

        $this->assertSame(0, Comment::count());
    }

    public function test_new_comment_notifies_the_activity_owner(): void
    {
        Notification::fake();

        $owner = $this->user('owner');
        $viewer = $this->user('viewer');
        $workout = $this->workout($owner, ActivityVisibility::Public);

        $this->actingAs($viewer)->post(route('comments.store', $workout), ['body' => 'Keren!']);

        Notification::assertSentTo($owner, NewComment::class);
    }

    // ---------------------------------------------------------------------
    // Follow / unfollow
    // ---------------------------------------------------------------------

    public function test_follow_persists(): void
    {
        $viewer = $this->user('viewer');
        $owner = $this->user('owner');

        $this->actingAs($viewer)->from(route('athletes.show', $owner))
            ->post(route('follows.store', $owner))
            ->assertRedirect(route('athletes.show', $owner));

        $this->assertDatabaseHas('follows', ['follower_id' => $viewer->id, 'following_id' => $owner->id]);
    }

    public function test_duplicate_follow_does_not_duplicate(): void
    {
        $viewer = $this->user('viewer');
        $owner = $this->user('owner');

        $this->actingAs($viewer)->post(route('follows.store', $owner));
        $this->actingAs($viewer)->post(route('follows.store', $owner))->assertRedirect();

        $this->assertSame(1, Follow::count());
    }

    public function test_a_user_cannot_follow_themselves(): void
    {
        $viewer = $this->user('viewer');

        $this->actingAs($viewer)->post(route('follows.store', $viewer))->assertForbidden();

        $this->assertSame(0, Follow::count());
    }

    public function test_unfollow_removes_the_follow(): void
    {
        $viewer = $this->user('viewer');
        $owner = $this->user('owner');
        Follow::create(['follower_id' => $viewer->id, 'following_id' => $owner->id]);

        $this->actingAs($viewer)->from(route('athletes.show', $owner))
            ->delete(route('follows.destroy', $owner))
            ->assertRedirect(route('athletes.show', $owner));

        $this->assertDatabaseMissing('follows', ['follower_id' => $viewer->id, 'following_id' => $owner->id]);
    }

    public function test_follow_notifies_the_followed_user(): void
    {
        Notification::fake();

        $viewer = $this->user('viewer');
        $owner = $this->user('owner');

        $this->actingAs($viewer)->post(route('follows.store', $owner));

        Notification::assertSentTo($owner, NewFollower::class);
    }

    public function test_unfollow_does_not_notify(): void
    {
        Notification::fake();

        $viewer = $this->user('viewer');
        $owner = $this->user('owner');
        Follow::create(['follower_id' => $viewer->id, 'following_id' => $owner->id]);

        $this->actingAs($viewer)->delete(route('follows.destroy', $owner));

        Notification::assertNotSentTo($owner, NewFollower::class);
    }

    // ---------------------------------------------------------------------
    // Visibilitas
    // ---------------------------------------------------------------------

    public function test_only_the_owner_may_change_visibility(): void
    {
        $owner = $this->user('owner');
        $stranger = $this->user('stranger');
        $workout = $this->workout($owner, ActivityVisibility::Private);

        // 404, bukan 403: keberadaan aktivitas orang lain tidak boleh terkonfirmasi.
        $this->actingAs($stranger)
            ->patch(route('activities.visibility.update', $workout), ['visibility' => 'public'])
            ->assertNotFound();

        $this->assertSame(ActivityVisibility::Private, $workout->fresh()->visibility);
    }

    public function test_the_owner_can_change_visibility(): void
    {
        $owner = $this->user('owner');
        $workout = $this->workout($owner, ActivityVisibility::Private);

        $this->actingAs($owner)->from(route('activities.show', $workout))
            ->patch(route('activities.visibility.update', $workout), ['visibility' => 'followers'])
            ->assertRedirect(route('activities.show', $workout));

        $this->assertSame(ActivityVisibility::Followers, $workout->fresh()->visibility);
    }

    public function test_an_invalid_visibility_value_is_rejected(): void
    {
        $owner = $this->user('owner');
        $workout = $this->workout($owner, ActivityVisibility::Private);

        $this->actingAs($owner)
            ->patch(route('activities.visibility.update', $workout), ['visibility' => 'bogus'])
            ->assertSessionHasErrors('visibility');

        $this->assertSame(ActivityVisibility::Private, $workout->fresh()->visibility);
    }

    // ---------------------------------------------------------------------
    // Halaman detail aktivitas
    // ---------------------------------------------------------------------

    public function test_activity_detail_returns_404_for_an_activity_the_viewer_may_not_see(): void
    {
        $owner = $this->user('owner');
        $viewer = $this->user('viewer');
        $workout = $this->workout($owner, ActivityVisibility::Private);

        $this->actingAs($viewer)->get(route('activities.show', $workout))->assertNotFound();
    }

    public function test_activity_detail_never_renders_health_or_recovery_strings(): void
    {
        $owner = $this->user('owner');
        $viewer = $this->user('viewer');
        $workout = $this->workout($owner, ActivityVisibility::Public);

        $this->actingAs($viewer)->get(route('activities.show', $workout))
            ->assertOk()
            ->assertDontSee('HRV')
            ->assertDontSee('readiness')
            ->assertDontSee('Body Battery');
    }

    public function test_activity_detail_shows_the_comment_thread(): void
    {
        $owner = $this->user('owner');
        $viewer = $this->user('viewer');
        $workout = $this->workout($owner, ActivityVisibility::Public);
        Comment::create(['user_id' => $viewer->id, 'workout_id' => $workout->id, 'body' => 'Semangat terus!']);

        $this->actingAs($viewer)->get(route('activities.show', $workout))
            ->assertOk()
            ->assertSee('Semangat terus!')
            ->assertSee('Komentar');
    }

    // ---------------------------------------------------------------------
    // K2 — data detak jantung hanya untuk pemilik
    // ---------------------------------------------------------------------

    public function test_a_non_owner_does_not_see_average_or_max_heart_rate(): void
    {
        $owner = $this->user('owner');
        $viewer = $this->user('viewer');
        $workout = $this->workout($owner, ActivityVisibility::Public);
        $workout->update(['average_heart_rate' => 142, 'max_heart_rate' => 176]);

        $this->actingAs($viewer)->get(route('activities.show', $workout))
            ->assertOk()
            ->assertDontSee('142 bpm')
            ->assertDontSee('176 bpm')
            ->assertDontSee('Avg HR')
            ->assertDontSee('Max HR')
            ->assertSee('Data detak jantung hanya terlihat oleh pemilik aktivitas.');
    }

    public function test_the_owner_sees_average_and_max_heart_rate(): void
    {
        $owner = $this->user('owner');
        $workout = $this->workout($owner, ActivityVisibility::Public);
        $workout->update(['average_heart_rate' => 142, 'max_heart_rate' => 176]);

        $this->actingAs($owner)->get(route('activities.show', $workout))
            ->assertOk()
            ->assertSee('142 bpm')
            ->assertSee('176 bpm')
            ->assertSee('Avg HR')
            ->assertSee('Max HR');
    }

    public function test_a_non_owner_does_not_receive_the_per_sample_heart_rate_series(): void
    {
        $owner = $this->user('owner');
        $viewer = $this->user('viewer');
        $workout = $this->workout($owner, ActivityVisibility::Public);
        $this->heartRateSample($workout, 142);

        $this->actingAs($viewer)->get(route('activities.show', $workout))
            ->assertOk()
            ->assertDontSee('Heart Rate')
            ->assertDontSee('142 bpm')
            ->assertViewHas('charts', fn (array $charts) => ! array_key_exists('heart_rate', $charts))
            ->assertViewHas('charts', fn (array $charts) => array_key_exists('pace', $charts));
    }

    public function test_the_owner_receives_the_per_sample_heart_rate_series(): void
    {
        $owner = $this->user('owner');
        $workout = $this->workout($owner, ActivityVisibility::Public);
        $this->heartRateSample($workout, 142);

        $this->actingAs($owner)->get(route('activities.show', $workout))
            ->assertOk()
            ->assertSee('Heart Rate')
            ->assertViewHas('charts', fn (array $charts) => array_key_exists('heart_rate', $charts)
                && $charts['heart_rate']['points'] !== []);
    }

    public function test_a_non_owner_does_not_see_per_lap_heart_rate(): void
    {
        $owner = $this->user('owner');
        $viewer = $this->user('viewer');
        $workout = $this->workout($owner, ActivityVisibility::Public);
        $this->lapWithHeartRate($workout, 155);

        $this->actingAs($viewer)->get(route('activities.show', $workout))
            ->assertOk()
            ->assertDontSee('155 bpm');
    }

    public function test_the_owner_sees_per_lap_heart_rate(): void
    {
        $owner = $this->user('owner');
        $workout = $this->workout($owner, ActivityVisibility::Public);
        $this->lapWithHeartRate($workout, 155);

        $this->actingAs($owner)->get(route('activities.show', $workout))
            ->assertOk()
            ->assertSee('155 bpm')
            ->assertSee('Lap 1');
    }

    public function test_a_non_owner_does_not_see_training_effect_or_training_load(): void
    {
        $owner = $this->user('owner');
        $viewer = $this->user('viewer');
        $workout = $this->workout($owner, ActivityVisibility::Public);
        $workout->update([
            'training_effect_aerobic' => 3.5,
            'training_effect_anaerobic' => 1.2,
            'training_effect_label' => 'improving',
            'training_load' => 120,
        ]);

        $this->actingAs($viewer)->get(route('activities.show', $workout))
            ->assertOk()
            ->assertDontSee('Training Effect')
            ->assertDontSee('Training Load');
    }

    public function test_the_owner_sees_training_effect_and_training_load(): void
    {
        $owner = $this->user('owner');
        $workout = $this->workout($owner, ActivityVisibility::Public);
        $workout->update([
            'training_effect_aerobic' => 3.5,
            'training_effect_anaerobic' => 1.2,
            'training_effect_label' => 'improving',
            'training_load' => 120,
        ]);

        $this->actingAs($owner)->get(route('activities.show', $workout))
            ->assertOk()
            ->assertSee('Training Effect')
            ->assertSee('Training Load');
    }

    // ---------------------------------------------------------------------
    // P6 — jelajah menghormati profil privat
    // ---------------------------------------------------------------------

    public function test_explore_does_not_suggest_a_private_profile_to_a_stranger(): void
    {
        $viewer = $this->user('viewer');
        $private = $this->user('rahasia');
        $private->update(['is_public' => false]);

        $this->actingAs($viewer)->get(route('explore'))
            ->assertOk()
            ->assertViewHas('suggestions', fn ($suggestions) => $suggestions->doesntContain('id', $private->id));
    }

    public function test_explore_does_not_suggest_a_profile_the_viewer_already_follows(): void
    {
        $viewer = $this->user('viewer');
        $followed = $this->user('sudahdiikuti');
        Follow::create(['follower_id' => $viewer->id, 'following_id' => $followed->id]);

        // Daftar ini khusus "atlet untuk diikuti", jadi orang yang sudah
        // diikuti tidak boleh muncul lagi — termasuk bila profilnya privat.
        $this->actingAs($viewer)->get(route('explore'))
            ->assertOk()
            ->assertViewHas('suggestions', fn ($suggestions) => $suggestions->doesntContain('id', $followed->id));
    }

    public function test_explore_still_suggests_a_public_profile(): void
    {
        $viewer = $this->user('viewer');
        $public = $this->user('terbuka');

        $this->actingAs($viewer)->get(route('explore'))
            ->assertOk()
            ->assertViewHas('suggestions', fn ($suggestions) => $suggestions->contains('id', $public->id));
    }

    // ---------------------------------------------------------------------
    // P3 — aktivitas followers-only
    // ---------------------------------------------------------------------

    public function test_a_followers_only_activity_is_404_for_a_non_follower(): void
    {
        $owner = $this->user('owner');
        $viewer = $this->user('viewer');
        $workout = $this->workout($owner, ActivityVisibility::Followers);

        $this->actingAs($viewer)->get(route('activities.show', $workout))->assertNotFound();
    }

    public function test_a_followers_only_activity_is_viewable_by_a_follower(): void
    {
        $owner = $this->user('owner');
        $viewer = $this->user('viewer');
        $workout = $this->workout($owner, ActivityVisibility::Followers);
        Follow::create(['follower_id' => $viewer->id, 'following_id' => $owner->id]);

        $this->actingAs($viewer)->get(route('activities.show', $workout))->assertOk();
    }

    private function user(string $username): User
    {
        return User::factory()->create([
            'name' => ucfirst($username).' Test',
            'username' => $username,
        ]);
    }

    private function workout(User $user, ActivityVisibility $visibility): Workout
    {
        $start = Carbon::parse('2026-09-01 06:00:00');

        return Workout::create([
            'user_id' => $user->id,
            'type' => 'running',
            'name' => 'Lari '.$user->name,
            'start_date' => $start,
            'end_date' => $start->copy()->addHour(),
            'distance_meters' => 10000,
            'source' => 'garmin',
            'visibility' => $visibility->value,
        ]);
    }

    private function heartRateSample(Workout $workout, int $heartRate): WorkoutSample
    {
        return WorkoutSample::create([
            'workout_id' => $workout->id,
            'timestamp' => $workout->start_date->copy()->addMinutes(5),
            'heart_rate' => $heartRate,
            'pace_seconds_per_km' => 300,
            'altitude_meters' => 12,
        ]);
    }

    private function lapWithHeartRate(Workout $workout, int $heartRate): WorkoutLap
    {
        return WorkoutLap::create([
            'workout_id' => $workout->id,
            'lap_index' => 1,
            'start_time' => $workout->start_date->copy(),
            'distance_meters' => 1000,
            'duration_seconds' => 300,
            'average_heart_rate' => $heartRate,
            'average_pace_seconds_per_km' => 300,
        ]);
    }
}
