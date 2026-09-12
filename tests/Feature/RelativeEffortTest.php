<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Workout;
use App\Models\WorkoutSample;
use App\Services\Training\RelativeEffortCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class RelativeEffortTest extends TestCase
{
    use RefreshDatabase;

    public function test_updates_relative_effort_from_per_sample_heart_rate(): void
    {
        $user = User::factory()->create();
        // HR maksimum pengguna = 200 bpm (dari max_heart_rate aktivitas).
        $workout = $this->makeWorkout($user, maxHeartRate: 200);

        // 10 menit di Z2 (130 bpm = 65% dari 200) → 10 × 2 = 20.
        $this->makeSamples($workout, from: 130, seconds: 600);

        $value = app(RelativeEffortCalculator::class)->updateWorkout($workout);

        $this->assertSame(20, $value);
        $this->assertSame(20, $workout->fresh()->relative_effort);
    }

    public function test_returns_null_without_heart_rate_samples(): void
    {
        $user = User::factory()->create();
        $workout = $this->makeWorkout($user, maxHeartRate: 200);

        $this->assertNull(app(RelativeEffortCalculator::class)->updateWorkout($workout));
        $this->assertNull($workout->fresh()->relative_effort);
    }

    public function test_command_backfills_workouts_with_heart_rate_samples(): void
    {
        $user = User::factory()->create();
        $scored = $this->makeWorkout($user, maxHeartRate: 200);
        $unscored = $this->makeWorkout($user, maxHeartRate: 200);

        $this->makeSamples($scored, from: 130, seconds: 600);

        $this->artisan('training:calculate-relative-effort')
            ->assertSuccessful();

        $this->assertSame(20, $scored->fresh()->relative_effort);
        $this->assertNull($unscored->fresh()->relative_effort);
    }

    public function test_score_reaches_activity_detail_page_for_owner(): void
    {
        $user = User::factory()->create();
        $workout = $this->makeWorkout($user, maxHeartRate: 200);
        $this->makeSamples($workout, from: 130, seconds: 600);
        app(RelativeEffortCalculator::class)->updateWorkout($workout);

        $this->actingAs($user)
            ->get(route('activities.show', $workout))
            ->assertOk()
            ->assertSee('Relative Effort')
            ->assertSee('20');
    }

    public function test_training_distribution_page_shows_period_total(): void
    {
        $user = User::factory()->create();
        $workout = $this->makeWorkout($user, maxHeartRate: 200);
        $this->makeSamples($workout, from: 130, seconds: 600);
        app(RelativeEffortCalculator::class)->updateWorkout($workout);

        // Tanggal aktivitas (2026-09-01) harus masuk jendela 90 hari terakhir.
        $this->travelTo(Carbon::parse('2026-09-05 12:00:00'));

        $this->actingAs($user)
            ->get(route('training.distribution', ['days' => 90]))
            ->assertOk()
            ->assertSee('Relative Effort')
            ->assertSee('20');
    }

    private function makeWorkout(User $user, int $maxHeartRate): Workout
    {
        return Workout::create([
            'user_id' => $user->id,
            'type' => 'running',
            'start_date' => Carbon::parse('2026-09-01 07:00:00'),
            'end_date' => Carbon::parse('2026-09-01 07:30:00'),
            'max_heart_rate' => $maxHeartRate,
            'source' => 'garmin',
        ]);
    }

    /**
     * Sampel HR dengan interval 20 detik selama $seconds, semuanya pada bpm yang
     * sama supaya zona yang dihasilkan tunggal dan mudah dihitung.
     */
    private function makeSamples(Workout $workout, int $from, int $seconds): void
    {
        $start = $workout->start_date->copy();

        for ($elapsed = 0; $elapsed <= $seconds; $elapsed += 20) {
            WorkoutSample::create([
                'workout_id' => $workout->id,
                'timestamp' => $start->copy()->addSeconds($elapsed),
                'heart_rate' => $from,
                'latitude' => null,
                'longitude' => null,
            ]);
        }
    }
}
