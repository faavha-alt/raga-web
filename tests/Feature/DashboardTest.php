<?php

namespace Tests\Feature;

use App\Models\ActivitySummary;
use App\Models\SleepSession;
use App\Models\User;
use App\Models\VitalMeasurement;
use App\Models\Workout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_loads_with_no_garmin_data(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
        $response->assertSee('Belum ada aktivitas tercatat.');
        $response->assertSee('Belum cukup data untuk insight');
    }

    public function test_dashboard_loads_with_full_garmin_data(): void
    {
        $user = User::factory()->create();

        for ($i = 0; $i < 14; $i++) {
            $date = Carbon::today()->subDays($i);

            VitalMeasurement::create([
                'user_id' => $user->id,
                'type' => 'resting_heart_rate',
                'value' => 60 + $i,
                'unit' => 'bpm',
                'date' => $date,
                'source' => 'garmin',
            ]);

            VitalMeasurement::create([
                'user_id' => $user->id,
                'type' => 'hrv_overnight_avg',
                'value' => 50,
                'unit' => 'ms',
                'date' => $date,
                'source' => 'garmin',
            ]);

            ActivitySummary::create([
                'user_id' => $user->id,
                'date' => $date,
                'steps' => 5000,
                'distance_meters' => 4000,
                'active_calories' => 300,
                'exercise_minutes' => 30,
                'source' => 'garmin',
            ]);

            SleepSession::create([
                'user_id' => $user->id,
                'bedtime' => $date->copy()->setTime(22, 30),
                'wake_time' => $date->copy()->addDay()->setTime(6, 30),
                'rem_minutes' => 90,
                'deep_minutes' => 60,
                'core_minutes' => 200,
                'awake_minutes' => 10,
                'sleep_score' => 80,
                'source' => 'garmin',
            ]);
        }

        VitalMeasurement::create([
            'user_id' => $user->id,
            'type' => 'training_readiness',
            'value' => 82,
            'unit' => 'score',
            'date' => Carbon::today(),
            'source' => 'garmin',
        ]);

        Workout::create([
            'user_id' => $user->id,
            'type' => 'running',
            'start_date' => Carbon::today()->setTime(6, 0),
            'end_date' => Carbon::today()->setTime(6, 30),
            'distance_meters' => 5000,
            'active_calories' => 320,
            'average_heart_rate' => 145,
            'elevation_gain_meters' => 50,
            'source' => 'garmin',
        ]);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
        $response->assertSee('Running');
        // Readiness now shows the computed Readiness Score (App\Services\Recovery),
        // not this raw Garmin vital directly - that scoring logic has its own
        // dedicated tests (RecoveryEngineTest, RecoveryScoreCalculatorTest etc).
    }
}
