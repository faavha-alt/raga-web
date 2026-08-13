<?php

namespace Tests\Feature;

use App\Models\PersonalRecord;
use App\Models\User;
use App\Models\Workout;
use App\Services\Running\RunningSeriesService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class RunningAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private const PAGES = ['/running', '/running/distance', '/running/pace', '/running/records'];

    public function test_all_running_pages_load_with_no_data(): void
    {
        $user = User::factory()->create();

        foreach (self::PAGES as $url) {
            $response = $this->actingAs($user)->get($url);
            $response->assertOk();
        }
    }

    public function test_all_running_pages_load_with_seeded_data(): void
    {
        $user = User::factory()->create();
        $this->seedMixedWorkouts($user);

        foreach (self::PAGES as $url) {
            $response = $this->actingAs($user)->get($url);
            $response->assertOk();
        }
    }

    public function test_performance_score_shows_insufficient_data_with_few_runs(): void
    {
        $user = User::factory()->create();
        $this->makeWorkout($user, 'running', Carbon::today(), pace: 300);

        $response = $this->actingAs($user)->get('/running');

        $response->assertOk();
        $response->assertSee('Belum cukup data');
    }

    public function test_series_totals_only_reflect_running_type_not_other_activities(): void
    {
        $user = User::factory()->create();
        $today = Carbon::today();

        // Running: 5km. Trail: 8km. Walking: 3km. Cycling: 20km.
        // Only the running distance should ever surface in RunningSeriesService.
        $this->makeWorkout($user, 'running', $today, distance: 5000);
        $this->makeWorkout($user, 'trail_running', $today, distance: 8000);
        $this->makeWorkout($user, 'walking', $today, distance: 3000);
        $this->makeWorkout($user, 'cycling', $today, distance: 20000);

        $totals = app(RunningSeriesService::class)->totalsForRange($user, $today->copy()->subDays(6), $today);

        $this->assertSame(5000.0, $totals['distance_meters']);
        $this->assertSame(1, $totals['activity_count']);
    }

    public function test_records_page_reuses_personal_record_table(): void
    {
        $user = User::factory()->create();

        PersonalRecord::create([
            'user_id' => $user->id,
            'type' => 'fastest_5k',
            'value' => 1500,
            'unit' => 'running_raw',
            'achieved_date' => Carbon::today(),
        ]);

        $response = $this->actingAs($user)->get('/running/records');

        $response->assertOk();
        $response->assertSee('Fastest 5K');
    }

    private function seedMixedWorkouts(User $user): void
    {
        for ($i = 0; $i < 10; $i += 2) {
            $date = Carbon::today()->subDays($i);
            $this->makeWorkout($user, 'running', $date, distance: 5000, pace: 300 - $i);
        }

        $this->makeWorkout($user, 'trail_running', Carbon::today()->subDay(), distance: 8000);
        $this->makeWorkout($user, 'walking', Carbon::today()->subDays(2), distance: 3000);
    }

    private function makeWorkout(User $user, string $type, Carbon $date, float $distance = 5000, ?float $pace = 300): Workout
    {
        $start = $date->copy()->setTime(7, 0);

        return Workout::create([
            'user_id' => $user->id,
            'type' => $type,
            'start_date' => $start,
            'end_date' => $start->copy()->addMinutes(30),
            'distance_meters' => $distance,
            'average_pace_seconds_per_km' => $pace,
            'average_heart_rate' => 140,
            'max_heart_rate' => 165,
            'elevation_gain_meters' => 20,
            'source' => 'garmin',
        ]);
    }
}
