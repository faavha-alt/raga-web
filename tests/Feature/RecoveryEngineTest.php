<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\VitalMeasurement;
use App\Models\Workout;
use App\Services\Health\PersonalBaselineService;
use App\Services\Recovery\RecoveryEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class RecoveryEngineTest extends TestCase
{
    use RefreshDatabase;

    public function test_recovery_page_loads_gracefully_with_no_data(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/recovery');

        $response->assertOk();
        $response->assertSee('40'); // base score with every factor insufficient
        $response->assertSee('Belum cukup data');
    }

    public function test_recovery_page_shows_the_non_medical_disclaimer(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/recovery');

        $response->assertOk();
        $response->assertSee(PersonalBaselineService::DISCLAIMER);
    }

    public function test_recovery_engine_persists_scores_with_seeded_history(): void
    {
        $user = User::factory()->create();
        $this->seedThirtyDaysOfVitals($user);

        $response = $this->actingAs($user)->get('/recovery');

        $response->assertOk();
        $this->assertDatabaseHas('recovery_scores', ['user_id' => $user->id, 'date' => Carbon::today()->toDateString()]);
        $this->assertDatabaseHas('readiness_scores', ['user_id' => $user->id, 'date' => Carbon::today()->toDateString()]);

        $recovery = $user->recoveryScores()->whereDate('date', Carbon::today())->first();
        $this->assertGreaterThanOrEqual(0, $recovery->score);
        $this->assertLessThanOrEqual(100, $recovery->score);
    }

    public function test_recovery_engine_is_idempotent_for_the_same_date(): void
    {
        $user = User::factory()->create();
        $this->seedThirtyDaysOfVitals($user);

        $engine = app(RecoveryEngine::class);
        $engine->calculateAndStoreForDate($user, Carbon::today());
        $engine->calculateAndStoreForDate($user, Carbon::today());

        $this->assertSame(1, $user->recoveryScores()->whereDate('date', Carbon::today())->count());
        $this->assertSame(1, $user->readinessScores()->whereDate('date', Carbon::today())->count());
    }

    public function test_recovery_score_category_reflects_the_score(): void
    {
        $user = User::factory()->create();
        $this->seedThirtyDaysOfVitals($user);

        app(RecoveryEngine::class)->calculateAndStoreForDate($user, Carbon::today());

        $recovery = $user->recoveryScores()->whereDate('date', Carbon::today())->first();
        $this->assertNotNull($recovery->category());
    }

    private function seedThirtyDaysOfVitals(User $user): void
    {
        for ($i = 0; $i < 30; $i++) {
            $date = Carbon::today()->subDays($i);

            $this->makeVital($user, 'hrv_overnight_avg', 50 + ($i % 5), $date);
            $this->makeVital($user, 'resting_heart_rate', 60 - ($i % 3), $date);
            $this->makeVital($user, 'stress_avg', 25 + ($i % 4), $date);
            $this->makeVital($user, 'body_battery_charged', 60, $date);
            $this->makeVital($user, 'body_battery_drained', 30, $date);

            \App\Models\SleepSession::create([
                'user_id' => $user->id,
                'bedtime' => $date->copy()->setTime(22, 30),
                'wake_time' => $date->copy()->addDay()->setTime(6, 30),
                'rem_minutes' => 90,
                'deep_minutes' => 70,
                'core_minutes' => 200,
                'awake_minutes' => 10,
                'source' => 'garmin',
            ]);
        }

        Workout::create([
            'user_id' => $user->id,
            'type' => 'running',
            'start_date' => Carbon::yesterday()->setTime(7, 0),
            'end_date' => Carbon::yesterday()->setTime(7, 30),
            'training_load' => 40,
            'source' => 'garmin',
        ]);
    }

    private function makeVital(User $user, string $type, float $value, Carbon $date): void
    {
        VitalMeasurement::create([
            'user_id' => $user->id,
            'type' => $type,
            'value' => $value,
            'unit' => 'unit',
            'date' => $date,
            'source' => 'garmin',
        ]);
    }
}
