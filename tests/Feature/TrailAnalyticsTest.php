<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Workout;
use App\Models\WorkoutSample;
use App\Services\Trail\TrailRouteGroupingService;
use App\Services\Trail\TrailSeriesService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class TrailAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private const PAGES = ['/trail', '/trail/routes'];

    public function test_all_trail_pages_load_with_no_data(): void
    {
        $user = User::factory()->create();

        foreach (self::PAGES as $url) {
            $response = $this->actingAs($user)->get($url);
            $response->assertOk();
        }
    }

    public function test_all_trail_pages_load_with_seeded_data(): void
    {
        $user = User::factory()->create();
        $this->makeWorkout($user, 'trail_running', Carbon::today(), name: 'Karanganyar Lari Trail', elevationGain: 500);
        $this->makeWorkout($user, 'running', Carbon::today()->subDay());

        foreach (self::PAGES as $url) {
            $response = $this->actingAs($user)->get($url);
            $response->assertOk();
        }
    }

    public function test_series_totals_only_reflect_trail_running_type_not_other_activities(): void
    {
        $user = User::factory()->create();
        $today = Carbon::today();

        $this->makeWorkout($user, 'running', $today, distance: 5000, elevationGain: 40);
        $this->makeWorkout($user, 'trail_running', $today, distance: 8000, elevationGain: 500);
        $this->makeWorkout($user, 'walking', $today, distance: 3000, elevationGain: 10);

        $totals = app(TrailSeriesService::class)->totalsForRange($user, $today->copy()->subDays(6), $today);

        $this->assertSame(8000.0, $totals['distance_meters']);
        $this->assertSame(500.0, $totals['elevation_gain_meters']);
        $this->assertSame(1, $totals['activity_count']);
    }

    public function test_show_page_404s_for_a_non_trail_workout(): void
    {
        $user = User::factory()->create();
        $workout = $this->makeWorkout($user, 'running', Carbon::today());

        $response = $this->actingAs($user)->get("/trail/{$workout->id}");

        $response->assertNotFound();
    }

    public function test_show_page_404s_for_another_users_workout(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $workout = $this->makeWorkout($otherUser, 'trail_running', Carbon::today());

        $response = $this->actingAs($user)->get("/trail/{$workout->id}");

        $response->assertNotFound();
    }

    public function test_show_page_renders_elevation_profile_with_gps_and_altitude_samples(): void
    {
        $user = User::factory()->create();
        $workout = $this->makeWorkout($user, 'trail_running', Carbon::today());

        $t = $workout->start_date->copy();
        foreach ([[-7.5, 110.8, 500], [-7.501, 110.8, 520], [-7.502, 110.8, 540]] as [$lat, $lng, $alt]) {
            WorkoutSample::create([
                'workout_id' => $workout->id,
                'timestamp' => $t->copy(),
                'latitude' => $lat,
                'longitude' => $lng,
                'altitude_meters' => $alt,
            ]);
            $t->addSeconds(30);
        }

        $response = $this->actingAs($user)->get("/trail/{$workout->id}");

        $response->assertOk();
        $response->assertSee('Elevation & Grade Profile', false);
        $response->assertDontSee('Belum cukup data GPS/elevasi');
    }

    public function test_route_grouping_only_groups_runs_with_the_exact_same_name(): void
    {
        $user = User::factory()->create();
        $this->makeWorkout($user, 'trail_running', Carbon::today()->subDays(14), name: 'Karanganyar Lari Trail', pace: 320);
        $this->makeWorkout($user, 'trail_running', Carbon::today(), name: 'Karanganyar Lari Trail', pace: 300);
        $this->makeWorkout($user, 'trail_running', Carbon::today()->subDays(7), name: 'Boyolali Lari Trail', pace: 310);

        $groups = app(TrailRouteGroupingService::class)->repeatedRoutes($user);

        $this->assertCount(1, $groups);
        $this->assertSame('Karanganyar Lari Trail', $groups[0]['name']);
        $this->assertCount(2, $groups[0]['runs']);

        $best = collect($groups[0]['runs'])->firstWhere('is_best', true);
        $this->assertSame(300.0, $best['workout']->average_pace_seconds_per_km);
    }

    public function test_route_grouping_page_shows_the_comparison_table(): void
    {
        $user = User::factory()->create();
        $this->makeWorkout($user, 'trail_running', Carbon::today()->subDays(14), name: 'Karanganyar Lari Trail', pace: 320);
        $this->makeWorkout($user, 'trail_running', Carbon::today(), name: 'Karanganyar Lari Trail', pace: 300);

        $response = $this->actingAs($user)->get('/trail/routes');

        $response->assertOk();
        $response->assertSee('Karanganyar Lari Trail');
        $response->assertSee('Terbaik');
    }

    private function makeWorkout(
        User $user,
        string $type,
        Carbon $date,
        float $distance = 6000,
        float $elevationGain = 400,
        ?string $name = null,
        ?float $pace = 320,
    ): Workout {
        $start = $date->copy()->setTime(7, 0);

        return Workout::create([
            'user_id' => $user->id,
            'type' => $type,
            'name' => $name,
            'start_date' => $start,
            'end_date' => $start->copy()->addHour(),
            'distance_meters' => $distance,
            'average_pace_seconds_per_km' => $pace,
            'average_heart_rate' => 140,
            'max_heart_rate' => 170,
            'elevation_gain_meters' => $elevationGain,
            'elevation_loss_meters' => $elevationGain * 0.9,
            'source' => 'garmin',
        ]);
    }
}
