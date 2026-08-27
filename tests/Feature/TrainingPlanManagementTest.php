<?php

namespace Tests\Feature;

use App\Models\TrainingPlan;
use App\Models\User;
use App\Models\TrainingWeek;
use App\Models\TrainingDay;
use App\Models\PlannedWorkout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrainingPlanManagementTest extends TestCase
{
    use RefreshDatabase;

    private function makePlan(User $user): TrainingPlan
    {
        $plan = $user->trainingPlans()->create([
            'name' => 'Half Marathon Build',
            'start_date' => '2026-09-01',
            'target_date' => '2026-09-14',
            'status' => 'active',
        ]);

        $week = $plan->weeks()->create([
            'week_number' => 1,
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-07',
        ]);

        $day = $week->days()->create(['date' => '2026-09-02']);

        $day->plannedWorkouts()->create([
            'type' => 'running',
            'distance_meters' => 5000,
            'intensity' => 'easy',
            'main_set' => '5km easy run',
        ]);

        return $plan;
    }

    public function test_plan_detail_shows_weeks_days_and_workouts(): void
    {
        $user = User::factory()->create();
        $plan = $this->makePlan($user);

        $this->actingAs($user)->get(route('training.plan', $plan))
            ->assertOk()
            ->assertSee('Half Marathon Build')
            ->assertSee('Minggu 1')
            ->assertSee('running')
            ->assertSee('5km easy run');
    }

    public function test_plan_detail_is_scoped_to_owner(): void
    {
        $owner = User::factory()->create();
        $plan = $this->makePlan($owner);

        $other = User::factory()->create();
        $this->actingAs($other)->get(route('training.plan', $plan))->assertForbidden();
    }

    public function test_owner_can_delete_plan(): void
    {
        $user = User::factory()->create();
        $plan = $this->makePlan($user);
        $planId = $plan->id;

        $this->actingAs($user)->delete(route('training.plan.destroy', $plan))
            ->assertRedirect(route('training'));

        $this->assertDatabaseMissing('training_plans', ['id' => $planId]);
        $this->assertDatabaseMissing('training_weeks', ['training_plan_id' => $planId]);
    }

    public function test_non_owner_cannot_delete_plan(): void
    {
        $owner = User::factory()->create();
        $plan = $this->makePlan($owner);

        $other = User::factory()->create();
        $this->actingAs($other)->delete(route('training.plan.destroy', $plan))->assertForbidden();

        $this->assertDatabaseHas('training_plans', ['id' => $plan->id]);
    }

    public function test_owner_can_mark_planned_workout_complete_and_undo(): void
    {
        $user = User::factory()->create();
        $plan = $this->makePlan($user);
        $pw = $plan->weeks->first()->days->first()->plannedWorkouts->first();

        $this->actingAs($user)->post(route('training.planned-workout.toggle', $pw))
            ->assertRedirect(route('training.plan', $plan));

        $this->assertDatabaseHas('completed_workouts', ['planned_workout_id' => $pw->id]);
        $fresh = $plan->fresh(['weeks.days.plannedWorkouts.completedWorkout']);
        $this->assertSame(1, $fresh->completedPlannedWorkouts());

        $this->actingAs($user)->post(route('training.planned-workout.toggle', $pw));
        $this->assertDatabaseMissing('completed_workouts', ['planned_workout_id' => $pw->id]);
        $this->assertSame(0, $plan->fresh(['weeks.days.plannedWorkouts.completedWorkout'])->completedPlannedWorkouts());
    }

    public function test_non_owner_cannot_toggle_planned_workout(): void
    {
        $owner = User::factory()->create();
        $plan = $this->makePlan($owner);
        $pw = $plan->weeks->first()->days->first()->plannedWorkouts->first();

        $other = User::factory()->create();
        $this->actingAs($other)->post(route('training.planned-workout.toggle', $pw))->assertForbidden();

        $this->assertDatabaseMissing('completed_workouts', ['planned_workout_id' => $pw->id]);
    }
}
