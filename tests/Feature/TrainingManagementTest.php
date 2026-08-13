<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Workout;
use App\Models\WorkoutSample;
use App\Services\Health\PersonalBaselineService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class TrainingManagementTest extends TestCase
{
    use RefreshDatabase;

    private const PAGES = ['/training', '/training/volume', '/training/load', '/training/calendar', '/training/distribution'];

    public function test_all_training_pages_load_with_no_data(): void
    {
        $user = User::factory()->create();

        foreach (self::PAGES as $url) {
            $response = $this->actingAs($user)->get($url);
            $response->assertOk();
        }
    }

    public function test_all_training_pages_load_with_seeded_data(): void
    {
        $user = User::factory()->create();
        $this->seedWorkouts($user);

        foreach (self::PAGES as $url) {
            $response = $this->actingAs($user)->get($url);
            $response->assertOk();
        }
    }

    public function test_calendar_for_a_month_with_zero_training_days_shows_all_rest_days(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/training/calendar?month=2020-01');

        $response->assertOk();
        $response->assertViewHas('calendar', function ($calendar) {
            return $calendar['summary']['active_days'] === 0
                && $calendar['summary']['rest_days'] === $calendar['summary']['total_days'];
        });
    }

    public function test_calendar_day_cell_links_to_activities_page_with_date_filters(): void
    {
        $user = User::factory()->create();
        $date = Carbon::today();

        Workout::create([
            'user_id' => $user->id,
            'type' => 'running',
            'start_date' => $date->copy()->setTime(7, 0),
            'end_date' => $date->copy()->setTime(7, 30),
            'source' => 'garmin',
        ]);

        $dateString = $date->toDateString();
        $response = $this->actingAs($user)->get("/activities?from={$dateString}&to={$dateString}");

        $response->assertOk();
    }

    public function test_distribution_shows_hr_zone_unavailable_message_without_any_hr_data(): void
    {
        $user = User::factory()->create();

        Workout::create([
            'user_id' => $user->id,
            'type' => 'strength_training',
            'start_date' => Carbon::today()->setTime(7, 0),
            'end_date' => Carbon::today()->setTime(7, 30),
            'source' => 'garmin',
        ]);

        $response = $this->actingAs($user)->get('/training/distribution');

        $response->assertOk();
        $response->assertSee('Belum ada data heart rate');
    }

    public function test_distribution_shows_hr_zones_when_sample_data_exists(): void
    {
        $user = User::factory()->create();

        $workout = Workout::create([
            'user_id' => $user->id,
            'type' => 'running',
            'start_date' => Carbon::today()->setTime(7, 0),
            'end_date' => Carbon::today()->setTime(7, 30),
            'max_heart_rate' => 180,
            'source' => 'garmin',
        ]);

        $t = Carbon::today()->setTime(7, 0);
        foreach ([120, 140, 160] as $bpm) {
            WorkoutSample::create([
                'workout_id' => $workout->id,
                'timestamp' => $t->copy(),
                'heart_rate' => $bpm,
            ]);
            $t->addSeconds(30);
        }

        $response = $this->actingAs($user)->get('/training/distribution');

        $response->assertOk();
        $response->assertSee('Estimasi HR maksimum');
        $response->assertDontSee('Belum ada data heart rate');
    }

    public function test_disclaimer_shown_on_load_page(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/training/load');

        $response->assertOk();
        $response->assertSee(PersonalBaselineService::DISCLAIMER);
    }

    private function seedWorkouts(User $user): void
    {
        for ($i = 0; $i < 20; $i += 2) {
            $date = Carbon::today()->subDays($i);

            Workout::create([
                'user_id' => $user->id,
                'type' => $i % 4 === 0 ? 'running' : 'cycling',
                'start_date' => $date->copy()->setTime(7, 0),
                'end_date' => $date->copy()->setTime(8, 0),
                'distance_meters' => 10000,
                'elevation_gain_meters' => 120,
                'average_heart_rate' => 140,
                'max_heart_rate' => 175,
                'training_load' => 60,
                'training_effect_aerobic' => 3.2,
                'training_effect_anaerobic' => 1.1,
                'training_effect_label' => 'Improving Aerobic Base',
                'source' => 'garmin',
            ]);
        }
    }
}
