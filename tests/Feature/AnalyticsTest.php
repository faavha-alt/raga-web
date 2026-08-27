<?php

namespace Tests\Feature;

use App\Models\RecoveryScore;
use App\Models\SleepSession;
use App\Models\User;
use App\Models\VitalMeasurement;
use App\Models\Workout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private const RELATIONSHIP_SLUGS = ['training-recovery', 'pace-heart-rate', 'training-load-performance'];

    public function test_all_analytics_pages_load_with_no_data(): void
    {
        $user = User::factory()->create();

        foreach ($this->allUrls() as $url) {
            $response = $this->actingAs($user)->get($url);
            $response->assertOk();
        }
    }

    public function test_all_analytics_pages_load_with_seeded_data(): void
    {
        $user = User::factory()->create();
        $this->seedCrossDomainData($user);

        foreach ($this->allUrls() as $url) {
            $response = $this->actingAs($user)->get($url);
            $response->assertOk();
        }
    }

    public function test_unknown_relationship_pair_404s(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/analytics/not-a-real-pair');

        $response->assertNotFound();
    }

    public function test_relationship_page_shows_a_real_correlation_with_sufficient_data(): void
    {
        $user = User::factory()->create();

        for ($i = 0; $i < 10; $i++) {
            $this->makeRunningWorkout($user, Carbon::today()->subDays($i), pace: 300 - $i, hr: 140 + $i);
        }

        $response = $this->actingAs($user)->get('/analytics/pace-heart-rate');

        $response->assertOk();
        $response->assertSee('tren teramati');
    }

    public function test_relationship_page_shows_insufficient_data_message_with_few_points(): void
    {
        $user = User::factory()->create();

        for ($i = 0; $i < 3; $i++) {
            $this->makeRunningWorkout($user, Carbon::today()->subDays($i), pace: 300, hr: 140);
        }

        $response = $this->actingAs($user)->get('/analytics/pace-heart-rate');

        $response->assertOk();
        $response->assertSee('Belum cukup data');
    }

    public function test_disclaimer_shown_on_relationship_page(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/analytics/training-recovery');

        $response->assertOk();
        $response->assertSee('Korelasi bukan bukti sebab-akibat', false);
    }

    /** @return list<string> */
    private function allUrls(): array
    {
        return array_merge(
            ['/analytics', '/analytics/health-trends', '/analytics/training-trends'],
            array_map(fn ($slug) => "/analytics/{$slug}", self::RELATIONSHIP_SLUGS),
        );
    }

    private function seedCrossDomainData(User $user): void
    {
        for ($i = 0; $i < 10; $i++) {
            $date = Carbon::today()->subDays($i);

            $this->makeRunningWorkout($user, $date, pace: 300 - $i, hr: 140 + $i);

            VitalMeasurement::create([
                'user_id' => $user->id,
                'type' => 'hrv_overnight_avg',
                'value' => 50 + $i,
                'unit' => 'ms',
                'date' => $date,
                'source' => 'garmin',
            ]);

            SleepSession::create([
                'user_id' => $user->id,
                'bedtime' => $date->copy()->setTime(22, 30),
                'wake_time' => $date->copy()->addDay()->setTime(6, 30),
                'rem_minutes' => 90,
                'deep_minutes' => 70,
                'core_minutes' => 200,
                'awake_minutes' => 10,
                'source' => 'garmin',
            ]);

            RecoveryScore::create([
                'user_id' => $user->id,
                'date' => $date,
                'score' => 50 + $i,
                'calculated_at' => now(),
            ]);
        }
    }

    private function makeRunningWorkout(User $user, Carbon $date, float $pace, float $hr): Workout
    {
        $start = $date->copy()->setTime(7, 0);

        return Workout::create([
            'user_id' => $user->id,
            'type' => 'running',
            'start_date' => $start,
            'end_date' => $start->copy()->addMinutes(30),
            'distance_meters' => 5000,
            'average_pace_seconds_per_km' => $pace,
            'average_heart_rate' => $hr,
            'training_load' => 50,
            'source' => 'garmin',
        ]);
    }
}
