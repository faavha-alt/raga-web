<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Workout;
use App\Services\Training\TrainingConsistencyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class TrainingConsistencyServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_zero_workouts_in_period(): void
    {
        $user = User::factory()->create();
        $from = Carbon::parse('2026-08-04');
        $to = Carbon::parse('2026-08-13');

        $result = app(TrainingConsistencyService::class)->forPeriod($user, $from, $to);

        $this->assertSame(10, $result['total_days']);
        $this->assertSame(0, $result['days_with_workout']);
        $this->assertSame(0.0, $result['consistency_percent']);
        $this->assertSame(10, $result['rest_days']);
        $this->assertSame(0, $result['current_streak_days']);
        $this->assertSame(0, $result['longest_streak_days']);
    }

    public function test_every_day_trained_gives_full_consistency_and_streak(): void
    {
        $user = User::factory()->create();
        $from = Carbon::parse('2026-08-04');
        $to = Carbon::parse('2026-08-13');

        for ($d = $from->copy(); $d->lte($to); $d->addDay()) {
            $this->makeWorkout($user, $d);
        }

        $result = app(TrainingConsistencyService::class)->forPeriod($user, $from, $to);

        $this->assertSame(100.0, $result['consistency_percent']);
        $this->assertSame(0, $result['rest_days']);
        $this->assertSame(10, $result['current_streak_days']);
        $this->assertSame(10, $result['longest_streak_days']);
    }

    public function test_streak_is_capped_to_the_queried_period(): void
    {
        $user = User::factory()->create();

        // Trained Aug 2 - Aug 10 (9 real-world consecutive days)...
        for ($d = Carbon::parse('2026-08-02'); $d->lte(Carbon::parse('2026-08-10')); $d->addDay()) {
            $this->makeWorkout($user, $d);
        }

        // ...but the queried period only starts Aug 4, so the streak must not extend past it.
        $from = Carbon::parse('2026-08-04');
        $to = Carbon::parse('2026-08-13');

        $result = app(TrainingConsistencyService::class)->forPeriod($user, $from, $to);

        $this->assertSame(7, $result['longest_streak_days']); // Aug 4-10 only, not Aug 2-10
        $this->assertSame(0, $result['current_streak_days']); // Aug 11-13 have no workouts
    }

    private function makeWorkout(User $user, Carbon $date): void
    {
        Workout::create([
            'user_id' => $user->id,
            'type' => 'running',
            'start_date' => $date->copy()->setTime(7, 0),
            'end_date' => $date->copy()->setTime(7, 30),
            'source' => 'garmin',
        ]);
    }
}
