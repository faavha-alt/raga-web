<?php

namespace App\Services\Dashboard;

use App\Models\User;
use Illuminate\Support\Carbon;

class WeeklyTrainingService
{
    /**
     * @return array{
     *     total_distance_meters: float,
     *     total_duration_seconds: int,
     *     total_elevation_meters: float,
     *     activity_count: int,
     *     daily_series: list<array{label: string, distance_meters: float, count: int}>,
     * }
     */
    public function summaryForUser(User $user): array
    {
        $start = Carbon::now()->startOfWeek();
        $end = Carbon::now()->endOfWeek();

        $workouts = $user->workouts()
            ->whereBetween('start_date', [$start, $end])
            ->get();

        $dailySeries = [];
        for ($day = $start->copy(); $day->lte($end); $day->addDay()) {
            $dayWorkouts = $workouts->filter(fn ($w) => $w->start_date->isSameDay($day));

            $dailySeries[] = [
                'label' => $day->translatedFormat('D'),
                'distance_meters' => (float) $dayWorkouts->sum('distance_meters'),
                'count' => $dayWorkouts->count(),
            ];
        }

        return [
            'total_distance_meters' => (float) $workouts->sum('distance_meters'),
            'total_duration_seconds' => (int) $workouts->sum(fn ($w) => $w->durationSeconds()),
            'total_elevation_meters' => (float) $workouts->sum('elevation_gain_meters'),
            'activity_count' => $workouts->count(),
            'daily_series' => $dailySeries,
        ];
    }
}
