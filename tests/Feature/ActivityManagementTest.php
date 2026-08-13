<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Workout;
use App\Models\WorkoutLap;
use App\Models\WorkoutSample;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ActivityManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_activity_list_loads_with_no_activities(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/activities');

        $response->assertOk();
        $response->assertSee('Tidak ada aktivitas yang cocok dengan filter ini.');
    }

    public function test_activity_list_shows_only_the_authenticated_users_workouts(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $this->makeWorkout($user, 'running', '2026-08-01');
        $this->makeWorkout($otherUser, 'cycling', '2026-08-01');

        $response = $this->actingAs($user)->get('/activities');

        $response->assertOk();
        $response->assertSee('Running');
        $response->assertDontSee('Cycling');
    }

    public function test_search_filters_by_type(): void
    {
        $user = User::factory()->create();
        $this->makeWorkout($user, 'running', '2026-08-01');
        $this->makeWorkout($user, 'road_biking', '2026-08-02');

        $response = $this->actingAs($user)->get('/activities?search=run');

        $response->assertOk();
        $response->assertViewHas('activities', fn ($p) => $p->count() === 1 && $p->first()->type === 'running');
    }

    public function test_type_filter_narrows_results(): void
    {
        $user = User::factory()->create();
        $this->makeWorkout($user, 'running', '2026-08-01');
        $this->makeWorkout($user, 'walking', '2026-08-02');

        $response = $this->actingAs($user)->get('/activities?type=walking');

        $response->assertOk();
        $response->assertViewHas('activities', fn ($p) => $p->count() === 1 && $p->first()->type === 'walking');
    }

    public function test_date_range_filter_narrows_results(): void
    {
        $user = User::factory()->create();
        $this->makeWorkout($user, 'running', '2026-01-01');
        $this->makeWorkout($user, 'walking', '2026-08-01');

        $response = $this->actingAs($user)->get('/activities?from=2026-07-01&to=2026-08-31');

        $response->assertOk();
        $response->assertViewHas('activities', fn ($p) => $p->count() === 1 && $p->first()->type === 'walking');
    }

    public function test_pagination_splits_results_across_pages(): void
    {
        $user = User::factory()->create();
        for ($i = 0; $i < 20; $i++) {
            $this->makeWorkout($user, 'running', Carbon::parse('2026-08-01')->addDays($i)->toDateString());
        }

        $response = $this->actingAs($user)->get('/activities');

        $response->assertOk();
        $response->assertViewHas('activities', fn ($paginator) => $paginator->total() === 20 && $paginator->perPage() === 15);
    }

    public function test_summary_totals_reflect_filtered_set(): void
    {
        $user = User::factory()->create();
        $this->makeWorkout($user, 'running', '2026-08-01', distance: 5000, calories: 300);
        $this->makeWorkout($user, 'walking', '2026-08-02', distance: 2000, calories: 100);

        $response = $this->actingAs($user)->get('/activities?type=running');

        $response->assertOk();
        $response->assertViewHas('summary', fn ($summary) => $summary['count'] === 1 && $summary['total_distance_meters'] === 5000.0);
    }

    public function test_activity_detail_page_shows_owned_workout(): void
    {
        $user = User::factory()->create();
        $workout = $this->makeWorkout($user, 'running', '2026-08-01');

        $response = $this->actingAs($user)->get("/activities/{$workout->id}");

        $response->assertOk();
        $response->assertSee('Running');
    }

    public function test_activity_detail_page_404s_for_another_users_workout(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $workout = $this->makeWorkout($otherUser, 'running', '2026-08-01');

        $response = $this->actingAs($user)->get("/activities/{$workout->id}");

        $response->assertNotFound();
    }

    public function test_activity_detail_renders_charts_when_samples_exist(): void
    {
        $user = User::factory()->create();
        $workout = $this->makeWorkout($user, 'running', '2026-08-01');

        WorkoutSample::create([
            'workout_id' => $workout->id,
            'timestamp' => $workout->start_date->copy()->addMinutes(5),
            'heart_rate' => 140,
            'pace_seconds_per_km' => 300,
            'altitude_meters' => 50,
            'cadence' => 170,
        ]);

        $response = $this->actingAs($user)->get("/activities/{$workout->id}");

        $response->assertOk();
        $response->assertSee('Heart Rate');
        $response->assertDontSee('Tidak ada data time-series');
    }

    public function test_activity_detail_handles_missing_samples_gracefully(): void
    {
        $user = User::factory()->create();
        $workout = $this->makeWorkout($user, 'running', '2026-08-01');

        $response = $this->actingAs($user)->get("/activities/{$workout->id}");

        $response->assertOk();
        $response->assertSee('Tidak ada data time-series');
    }

    public function test_activity_detail_shows_new_garmin_fields_when_present(): void
    {
        $user = User::factory()->create();
        $start = Carbon::parse('2026-08-01')->setTime(7, 0);

        $workout = Workout::create([
            'user_id' => $user->id,
            'type' => 'running',
            'name' => 'Morning Tempo Run',
            'start_date' => $start,
            'end_date' => $start->copy()->addMinutes(30),
            'distance_meters' => 5000,
            'active_calories' => 300,
            'elevation_gain_meters' => 20,
            'elevation_loss_meters' => 18,
            'training_effect_aerobic' => 3.2,
            'training_effect_anaerobic' => 1.1,
            'training_effect_label' => 'AEROBIC_BASE',
            'training_load' => 45.6,
            'source' => 'garmin',
        ]);

        $response = $this->actingAs($user)->get("/activities/{$workout->id}");

        $response->assertOk();
        $response->assertSee('Morning Tempo Run');
        $response->assertSee('Elevation Loss');
        $response->assertSee('Training Effect');
        $response->assertSee('Training Load (Garmin)');
    }

    public function test_activity_detail_omits_new_fields_gracefully_when_absent(): void
    {
        $user = User::factory()->create();
        $workout = $this->makeWorkout($user, 'running', '2026-08-01');

        $response = $this->actingAs($user)->get("/activities/{$workout->id}");

        $response->assertOk();
        // Falls back to the type-based title since no name was synced for this row.
        $response->assertSee('Running');
        $response->assertDontSee('Training Effect');
        $response->assertDontSee('Training Load (Garmin)');
    }

    public function test_activity_detail_shows_route_map_when_gps_samples_exist(): void
    {
        $user = User::factory()->create();
        $workout = $this->makeWorkout($user, 'running', '2026-08-01');

        foreach ([[-7.5587, 110.8584], [-7.5590, 110.8590]] as [$lat, $lng]) {
            WorkoutSample::create([
                'workout_id' => $workout->id,
                'timestamp' => $workout->start_date->copy()->addMinutes(1),
                'latitude' => $lat,
                'longitude' => $lng,
            ]);
        }

        $response = $this->actingAs($user)->get("/activities/{$workout->id}");

        $response->assertOk();
        $response->assertSee('x-ref="map"', false);
    }

    public function test_activity_detail_shows_laps_when_present(): void
    {
        $user = User::factory()->create();
        $workout = $this->makeWorkout($user, 'running', '2026-08-01');

        WorkoutLap::create([
            'workout_id' => $workout->id,
            'lap_index' => 1,
            'start_time' => $workout->start_date,
            'distance_meters' => 1000,
            'duration_seconds' => 300,
            'average_heart_rate' => 145,
        ]);

        $response = $this->actingAs($user)->get("/activities/{$workout->id}");

        $response->assertOk();
        $response->assertSee('Laps');
        $response->assertSee('Lap 1');
    }

    private function makeWorkout(User $user, string $type, string $date, float $distance = 5000, float $calories = 300): Workout
    {
        $start = Carbon::parse($date)->setTime(7, 0);

        return Workout::create([
            'user_id' => $user->id,
            'type' => $type,
            'start_date' => $start,
            'end_date' => $start->copy()->addMinutes(30),
            'distance_meters' => $distance,
            'active_calories' => $calories,
            'average_heart_rate' => 140,
            'max_heart_rate' => 165,
            'elevation_gain_meters' => 20,
            'source' => 'garmin',
        ]);
    }
}
