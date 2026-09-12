<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Workout;
use App\Models\WorkoutSample;
use App\Services\Activity\ActivityDetailService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Split per kilometer + profil elevasi berband gradien pada halaman detail
 * aktivitas, termasuk aturan privasi kolom detak jantung.
 */
class ActivitySplitTest extends TestCase
{
    use RefreshDatabase;

    public function test_splits_are_built_per_kilometer_from_pace_when_gps_is_missing(): void
    {
        $user = User::factory()->create();
        $workout = $this->makeWorkout($user);

        // 20 menit @ 5:00/km = 4 km, sampel tiap 30 detik (100 m per interval).
        $this->makeSamples($workout, seconds: 1200, intervalSeconds: 30, pace: 300, heartRate: 150);

        $splits = app(ActivityDetailService::class)->splitsFor($workout, true);

        $this->assertCount(4, $splits);
        $this->assertSame(1, $splits[0]['index']);
        $this->assertSame(1.0, $splits[0]['distance_km']);
        $this->assertSame(300, $splits[0]['duration_seconds']);
        $this->assertSame(300, $splits[0]['pace_seconds_per_km']);
        $this->assertSame('fastest', $splits[0]['tag']);
    }

    public function test_split_heart_rate_is_omitted_when_not_requested(): void
    {
        $user = User::factory()->create();
        $workout = $this->makeWorkout($user);
        $this->makeSamples($workout, seconds: 1200, intervalSeconds: 30, pace: 300, heartRate: 150);

        $splits = app(ActivityDetailService::class)->splitsFor($workout, false);

        $this->assertNotNull($splits);
        $this->assertNull($splits[0]['avg_heart_rate']);

        $withHeartRate = app(ActivityDetailService::class)->splitsFor($workout, true);
        $this->assertSame(150.0, $withHeartRate[0]['avg_heart_rate']);
    }

    public function test_elevation_profile_computes_gain_loss_and_grade(): void
    {
        $user = User::factory()->create();
        $workout = $this->makeWorkout($user);

        // Naik 10 m tiap 100 m jarak → grade +10%, total naik 100 m.
        $this->makeSamples($workout, seconds: 1200, intervalSeconds: 30, pace: 300, heartRate: null, altitudeStart: 100, altitudeStep: 10);

        $profile = app(ActivityDetailService::class)->elevationProfileWithGrade($workout);

        $this->assertTrue($profile['available']);
        // 40 interval × 10 m = 400 m naik, tanpa turun.
        $this->assertSame(400.0, $profile['gain_meters']);
        $this->assertSame(0.0, $profile['loss_meters']);
        $this->assertSame(10.0, $profile['points'][1]['grade']);
        $this->assertSame(0.1, $profile['points'][1]['distance_km']);
    }

    public function test_split_section_is_rendered_for_the_workout_owner(): void
    {
        $user = User::factory()->create();
        $workout = $this->makeWorkout($user);
        $this->makeSamples($workout, seconds: 1200, intervalSeconds: 30, pace: 300, heartRate: 150);

        $this->actingAs($user)
            ->get("/activities/{$workout->id}")
            ->assertOk()
            ->assertSee('Split Per Kilometer')
            ->assertSee('Tercepat')
            ->assertSee('>HR<', false);
    }

    public function test_split_heart_rate_column_is_hidden_from_other_viewers(): void
    {
        $owner = User::factory()->create();
        $viewer = User::factory()->create();
        $workout = $this->makeWorkout($owner);
        $workout->forceFill(['visibility' => 'public'])->save();

        $this->makeSamples($workout, seconds: 1200, intervalSeconds: 30, pace: 300, heartRate: 150);

        $this->actingAs($viewer)
            ->get("/activities/{$workout->id}")
            ->assertOk()
            ->assertSee('Split Per Kilometer')
            ->assertDontSee('>HR<', false);
    }

    public function test_grade_profile_marks_uphill_and_downhill_bands(): void
    {
        $user = User::factory()->create();
        $workout = $this->makeWorkout($user);

        $this->makeSamples($workout, seconds: 900, intervalSeconds: 30, pace: 300, heartRate: null, altitudeStart: 0, altitudeStep: 20);

        $profile = app(ActivityDetailService::class)->elevationProfileWithGrade($workout);
        $grades = array_column($profile['points'], 'grade');

        // 20 m naik per 100 m jarak = +20% → masuk band "≥ 5% naik".
        $this->assertContains(20.0, $grades);
    }

    private function makeWorkout(User $user): Workout
    {
        return Workout::create([
            'user_id' => $user->id,
            'type' => 'running',
            'start_date' => Carbon::parse('2026-09-01 07:00:00'),
            'end_date' => Carbon::parse('2026-09-01 07:30:00'),
            'distance_meters' => 4000,
            'source' => 'garmin',
        ]);
    }

    /**
     * Sampel berurutan dengan interval tetap. Tanpa GPS: jarak diturunkan dari
     * pace oleh service, sehingga angkanya deterministik untuk test.
     */
    private function makeSamples(
        Workout $workout,
        int $seconds,
        int $intervalSeconds,
        ?float $pace,
        ?float $heartRate,
        ?float $altitudeStart = null,
        float $altitudeStep = 0.0,
    ): void {
        $start = $workout->start_date->copy();

        for ($elapsed = 0, $step = 0; $elapsed <= $seconds; $elapsed += $intervalSeconds, $step++) {
            WorkoutSample::create([
                'workout_id' => $workout->id,
                'timestamp' => $start->copy()->addSeconds($elapsed),
                'heart_rate' => $heartRate,
                'pace_seconds_per_km' => $pace,
                'altitude_meters' => $altitudeStart !== null ? $altitudeStart + ($step * $altitudeStep) : null,
            ]);
        }
    }
}
