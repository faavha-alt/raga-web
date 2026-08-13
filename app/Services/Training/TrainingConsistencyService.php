<?php

namespace App\Services\Training;

use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Rest-day / consistency stats — plain count-based math, no baselines or
 * z-scores needed. A "rest day" is simply a calendar day in the period
 * with zero Workout rows; no such flag exists in the schema so it's
 * derived here rather than stored.
 */
class TrainingConsistencyService
{
    /**
     * @return array{
     *     days_with_workout: int,
     *     total_days: int,
     *     consistency_percent: float,
     *     rest_days: int,
     *     current_streak_days: int,
     *     longest_streak_days: int,
     * }
     */
    public function forPeriod(User $user, Carbon $from, Carbon $to): array
    {
        $from = $from->copy()->startOfDay();
        $to = $to->copy()->startOfDay();

        $trainedDates = $user->workouts()
            ->whereBetween('start_date', [$from, $to->copy()->endOfDay()])
            ->selectRaw('DISTINCT DATE(start_date) as workout_date')
            ->pluck('workout_date')
            ->all();

        $trainedSet = array_flip($trainedDates);
        $totalDays = (int) $from->diffInDays($to) + 1;
        $daysWithWorkout = count($trainedDates);

        [$currentStreak, $longestStreak] = $this->streaks($trainedSet, $from, $to);

        return [
            'days_with_workout' => $daysWithWorkout,
            'total_days' => $totalDays,
            'consistency_percent' => $totalDays > 0 ? round($daysWithWorkout / $totalDays * 100, 1) : 0.0,
            'rest_days' => $totalDays - $daysWithWorkout,
            'current_streak_days' => $currentStreak,
            'longest_streak_days' => $longestStreak,
        ];
    }

    /**
     * Streaks are capped to the queried period — a streak that began before
     * $from is not counted any further back than $from itself.
     *
     * @param  array<string,int>  $trainedSet
     * @return array{0:int,1:int} [current_streak, longest_streak]
     */
    private function streaks(array $trainedSet, Carbon $from, Carbon $to): array
    {
        $longest = 0;
        $running = 0;

        $cursor = $from->copy();
        while ($cursor->lte($to)) {
            if (isset($trainedSet[$cursor->toDateString()])) {
                $running++;
                $longest = max($longest, $running);
            } else {
                $running = 0;
            }
            $cursor->addDay();
        }

        $current = 0;
        $cursor = $to->copy();
        while ($cursor->gte($from) && isset($trainedSet[$cursor->toDateString()])) {
            $current++;
            $cursor->subDay();
        }

        return [$current, $longest];
    }
}
