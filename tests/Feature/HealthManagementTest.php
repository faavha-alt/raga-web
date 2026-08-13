<?php

namespace Tests\Feature;

use App\Models\ActivitySummary;
use App\Models\HeartRateSample;
use App\Models\User;
use App\Models\VitalMeasurement;
use App\Services\Health\PersonalBaselineService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class HealthManagementTest extends TestCase
{
    use RefreshDatabase;

    private const PAGES = [
        '/health' => 'health',
        '/health/heart' => 'health.heart',
        '/health/stress' => 'health.stress',
        '/health/body-battery' => 'health.body-battery',
        '/health/daily-metrics' => 'health.daily-metrics',
    ];

    public function test_all_health_pages_load_with_no_data(): void
    {
        $user = User::factory()->create();

        foreach (self::PAGES as $url => $viewPrefix) {
            $response = $this->actingAs($user)->get($url);
            $response->assertOk();
        }
    }

    public function test_all_health_pages_load_with_seeded_data(): void
    {
        $user = User::factory()->create();
        $this->seedTwoWeeksOfVitals($user);

        foreach (array_keys(self::PAGES) as $url) {
            $response = $this->actingAs($user)->get($url);
            $response->assertOk();
        }
    }

    public function test_daily_metrics_page_offers_recovery_time(): void
    {
        $user = User::factory()->create();
        $this->makeVital($user, 'recovery_time', 12, Carbon::today());

        $response = $this->actingAs($user)->get('/health/daily-metrics');

        $response->assertOk();
        $response->assertSee('Recovery Time');
    }

    public function test_heart_page_offers_1y_range(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/health/heart');

        $response->assertOk();
        $response->assertSee('1Y');
    }

    public function test_average_and_max_heart_rate_are_computed_correctly_from_samples(): void
    {
        $user = User::factory()->create();
        $today = Carbon::today()->setTime(8, 0);

        foreach ([100, 110, 120] as $bpm) {
            HeartRateSample::create([
                'user_id' => $user->id,
                'timestamp' => $today,
                'bpm' => $bpm,
                'is_resting' => false,
                'source' => 'garmin',
            ]);
            $today->addMinute();
        }

        $service = app(\App\Services\Health\MetricSeriesService::class);
        $avg = $service->latest($user, 'avg_hr');
        $max = $service->latest($user, 'max_hr');

        $this->assertEqualsWithDelta(110.0, $avg['value'], 0.01);
        $this->assertEquals(120.0, $max['value']);
    }

    public function test_baseline_is_null_below_minimum_sample_count(): void
    {
        $user = User::factory()->create();

        for ($i = 0; $i < 6; $i++) {
            $this->makeVital($user, 'resting_heart_rate', 60 + $i, Carbon::today()->subDays($i));
        }

        $baseline = app(PersonalBaselineService::class)->compute($user, 'resting_hr');

        $this->assertNull($baseline);
    }

    public function test_baseline_is_computed_once_minimum_sample_count_is_reached(): void
    {
        $user = User::factory()->create();

        for ($i = 0; $i < 7; $i++) {
            $this->makeVital($user, 'resting_heart_rate', 60 + $i, Carbon::today()->subDays($i));
        }

        $baseline = app(PersonalBaselineService::class)->compute($user, 'resting_hr');

        $this->assertNotNull($baseline);
        $this->assertSame(7, $baseline['sample_count']);
        $this->assertDatabaseHas('personal_baselines', [
            'user_id' => $user->id,
            'metric_type' => 'resting_hr',
            'sample_count' => 7,
        ]);
    }

    public function test_baseline_callout_always_shows_the_non_medical_disclaimer(): void
    {
        $user = User::factory()->create();
        for ($i = 0; $i < 10; $i++) {
            $this->makeVital($user, 'resting_heart_rate', 60 + $i, Carbon::today()->subDays($i));
        }

        $response = $this->actingAs($user)->get('/health/heart');

        $response->assertOk();
        $response->assertSee('baseline', false);
        $response->assertSee(PersonalBaselineService::DISCLAIMER);
    }

    public function test_overview_shows_no_baseline_note_without_sufficient_history(): void
    {
        $user = User::factory()->create();
        $this->makeVital($user, 'resting_heart_rate', 60, Carbon::today());

        $response = $this->actingAs($user)->get('/health');

        $response->assertOk();
        $response->assertDontSee('vs baseline 30D');
    }

    private function seedTwoWeeksOfVitals(User $user): void
    {
        for ($i = 0; $i < 14; $i++) {
            $date = Carbon::today()->subDays($i);

            $this->makeVital($user, 'resting_heart_rate', 60 + $i, $date);
            $this->makeVital($user, 'hrv_overnight_avg', 50, $date);
            $this->makeVital($user, 'stress_avg', 30, $date);
            $this->makeVital($user, 'respiration_rate', 15, $date);
            $this->makeVital($user, 'spo2_avg', 97, $date);
            $this->makeVital($user, 'body_battery_charged', 60, $date);
            $this->makeVital($user, 'body_battery_drained', 40, $date);

            HeartRateSample::create([
                'user_id' => $user->id,
                'timestamp' => $date->copy()->setTime(8, 0),
                'bpm' => 100,
                'is_resting' => false,
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
        }
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
