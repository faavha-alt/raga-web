<?php

namespace App\Services\Dashboard;

use App\Models\User;
use Illuminate\Support\Carbon;

class WeeklyTrainingService
{
    /**
     * Deret 7 hari terakhir (termasuk hari ini) — dipakai kartu "puncak beban".
     * Berbeda dari `summaryForUser()` yang memakai minggu kalender: di sini
     * jendelanya selalu 7 hari ke belakang, jadi tidak ada hari kosong di depan.
     *
     * @return list<array{label: string, date: string, distance_meters: float, relative_effort: int, count: int}>
     */
    public function lastSevenDays(User $user): array
    {
        $today = Carbon::today();
        $start = $today->copy()->subDays(6);

        $workouts = $user->workouts()
            ->whereBetween('start_date', [$start->copy()->startOfDay(), $today->copy()->endOfDay()])
            ->get();

        $series = [];
        for ($day = $start->copy(); $day->lte($today); $day->addDay()) {
            $dayWorkouts = $workouts->filter(fn ($w) => $w->start_date->isSameDay($day));

            $series[] = [
                'label' => $day->translatedFormat('D'),
                'date' => $day->toDateString(),
                'distance_meters' => (float) $dayWorkouts->sum('distance_meters'),
                'relative_effort' => (int) $dayWorkouts->sum('relative_effort'),
                'count' => $dayWorkouts->count(),
            ];
        }

        return $series;
    }

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
