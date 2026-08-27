<?php

namespace Tests\Feature;

use App\Models\TrainingGoal;
use App\Models\User;
use App\Models\Workout;
use App\Services\Training\TrainingGoalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class GoalManagementTest extends TestCase
{
    use RefreshDatabase;

    private function addWorkout(User $user, Carbon $when, float $km): Workout
    {
        $meters = $km * 1000;

        return $user->workouts()->create([
            'type' => 'running',
            'start_date' => $when,
            'end_date' => $when->copy()->addMinutes(30),
            'distance_meters' => $meters,
            'source' => 'garmin',
        ]);
    }

    public function test_goals_page_loads_with_no_goals(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/goals')->assertOk()->assertSee('Belum Ada Goal');
    }

    public function test_store_creates_goal(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/goals', [
            'type' => 'weekly_distance',
            'target_value' => 30,
            'target_date' => '2026-10-01',
        ])->assertRedirect(route('goals.index'));

        $this->assertDatabaseHas('training_goals', ['user_id' => $user->id, 'type' => 'weekly_distance', 'target_value' => 30]);
    }

    public function test_store_rejects_invalid_goal_type(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/goals', ['type' => 'bogus', 'target_value' => 10])
            ->assertSessionHasErrors('type');

        $this->assertSame(0, TrainingGoal::count());
    }

    public function test_owner_can_delete_goal(): void
    {
        $user = User::factory()->create();
        $goal = $user->trainingGoals()->create(['type' => 'custom', 'custom_description' => 'PR 10K']);
        $id = $goal->id;

        $this->actingAs($user)->delete(route('goals.destroy', $goal))->assertRedirect(route('goals.index'));

        $this->assertDatabaseMissing('training_goals', ['id' => $id]);
    }

    public function test_non_owner_cannot_delete_goal(): void
    {
        $owner = User::factory()->create();
        $goal = $owner->trainingGoals()->create(['type' => 'custom', 'custom_description' => 'PR 10K']);

        $other = User::factory()->create();
        $this->actingAs($other)->delete(route('goals.destroy', $goal))->assertForbidden();

        $this->assertDatabaseHas('training_goals', ['id' => $goal->id]);
    }

    public function test_weekly_distance_progress_uses_this_weeks_workouts(): void
    {
        $user = User::factory()->create();
        $this->addWorkout($user, Carbon::now(), 3.0);
        $this->addWorkout($user, Carbon::now(), 2.0);

        $goal = $user->trainingGoals()->create(['type' => 'weekly_distance', 'target_value' => 10]);

        $progress = app(TrainingGoalService::class)->progressFor($user, $goal);

        $this->assertSame(5.0, $progress['current']);
        $this->assertSame(50, $progress['percent']);
    }
}
