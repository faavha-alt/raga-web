<?php

namespace App\Services\Trail;

use App\Models\User;
use App\Models\Workout;
use Illuminate\Support\Collection;

/**
 * Groups trail_running workouts by exact non-null name (Garmin's activity
 * name is free text, but real data for this account shows names repeat
 * for the same physical route — e.g. "Karanganyar Lari Trail" x2). Only
 * groups with 2+ runs count as "repeated routes". Each run in a group is
 * compared against the group's best (fastest average pace) run.
 */
class TrailRouteGroupingService
{
    private const TYPE = 'trail_running';

    /**
     * @return list<array{
     *     name: string,
     *     runs: list<array{workout: Workout, is_best: bool, pace_delta_seconds: ?float}>,
     * }>
     */
    public function repeatedRoutes(User $user): array
    {
        $workouts = $user->workouts()
            ->where('type', self::TYPE)
            ->whereNotNull('name')
            ->orderBy('start_date')
            ->get();

        return $workouts
            ->groupBy('name')
            ->filter(fn (Collection $group) => $group->count() >= 2)
            ->map(function (Collection $group, string $name) {
                $best = $group
                    ->filter(fn ($w) => $w->average_pace_seconds_per_km !== null)
                    ->sortBy('average_pace_seconds_per_km')
                    ->first();

                $runs = $group->map(fn ($workout) => [
                    'workout' => $workout,
                    'is_best' => $best && $workout->is($best),
                    'pace_delta_seconds' => ($best && $workout->average_pace_seconds_per_km !== null)
                        ? $workout->average_pace_seconds_per_km - $best->average_pace_seconds_per_km
                        : null,
                ])->values()->all();

                return ['name' => $name, 'runs' => $runs];
            })
            ->values()
            ->all();
    }
}
