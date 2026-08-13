<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Workout;
use App\Services\Training\TrainingStatusEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class TrainingLoadEngineTest extends TestCase
{
    use RefreshDatabase;

    public function test_training_load_page_loads_gracefully_with_no_workouts(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/training/load');

        $response->assertOk();
        $response->assertSee('Data Belum Cukup');
    }

    public function test_engine_persists_sane_scores_with_seeded_history(): void
    {
        $user = User::factory()->create();
        $this->seedThirtyDaysOfTraining($user);

        $result = app(TrainingStatusEngine::class)->calculateAndStoreForDate($user, Carbon::today());

        $this->assertGreaterThanOrEqual(0.0, $result->acute_load);
        $this->assertGreaterThanOrEqual(0.0, $result->chronic_load);
        $this->assertNotNull($result->acute_chronic_ratio);
        $this->assertContains($result->risk_level, ['undertraining', 'optimal', 'caution', 'high_risk']);
    }

    public function test_engine_is_idempotent_for_the_same_date(): void
    {
        $user = User::factory()->create();
        $this->seedThirtyDaysOfTraining($user);

        $engine = app(TrainingStatusEngine::class);
        $engine->calculateAndStoreForDate($user, Carbon::today());
        $engine->calculateAndStoreForDate($user, Carbon::today());

        $this->assertSame(1, $user->trainingLoads()->whereDate('date', Carbon::today())->count());
    }

    private function seedThirtyDaysOfTraining(User $user): void
    {
        for ($i = 0; $i < 30; $i += 2) {
            $date = Carbon::today()->subDays($i);

            Workout::create([
                'user_id' => $user->id,
                'type' => 'running',
                'start_date' => $date->copy()->setTime(7, 0),
                'end_date' => $date->copy()->setTime(7, 45),
                'distance_meters' => 8000,
                'training_load' => 50 + ($i % 5) * 10,
                'source' => 'garmin',
            ]);
        }
    }
}
