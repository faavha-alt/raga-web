<?php

namespace App\Services\Training;

use App\Models\PlannedWorkout;
use App\Models\User;
use App\Support\ActivityTypeIcon;
use Illuminate\Support\Carbon;

class TrainingCalendarService
{
    /**
     * @return array{
     *     month_label: string,
     *     prev_month: string,
     *     next_month: string,
     *     days: list<array{date:string,day:int,in_month:bool,is_today:bool,is_rest_day:bool,workouts:list<array{id:int,type:string,icon:string,distance_meters:?float}>,planned_workouts:list<array{type:string,icon:string}>}>,
     *     summary: array{total_days:int,active_days:int,rest_days:int,total_distance_meters:float,total_duration_seconds:int},
     * }
     */
    public function forMonth(User $user, Carbon $month): array
    {
        $monthStart = $month->copy()->startOfMonth();
        $monthEnd = $month->copy()->endOfMonth();

        $gridStart = $monthStart->copy()->startOfWeek();
        $gridEnd = $monthEnd->copy()->endOfWeek();

        $workoutsByDate = $user->workouts()
            ->whereBetween('start_date', [$monthStart, $monthEnd->copy()->endOfDay()])
            ->get()
            ->groupBy(fn ($w) => $w->start_date->toDateString());

        $plannedByDate = PlannedWorkout::query()
            ->whereHas('day', function ($query) use ($user, $monthStart, $monthEnd) {
                $query->whereBetween('date', [$monthStart, $monthEnd])
                    ->whereHas('week.plan', fn ($q) => $q->where('user_id', $user->id));
            })
            ->with('day')
            ->get()
            ->groupBy(fn ($pw) => $pw->day->date->toDateString());

        $today = Carbon::today();
        $days = [];
        $activeDays = 0;
        $totalDistance = 0.0;
        $totalDurationSeconds = 0;

        $cursor = $gridStart->copy();
        while ($cursor->lte($gridEnd)) {
            $dateKey = $cursor->toDateString();
            $inMonth = $cursor->month === $monthStart->month;
            $dayWorkouts = $workoutsByDate->get($dateKey, collect());

            if ($inMonth && $dayWorkouts->isNotEmpty()) {
                $activeDays++;

                foreach ($dayWorkouts as $workout) {
                    $totalDistance += (float) ($workout->distance_meters ?? 0);
                    $totalDurationSeconds += $workout->durationSeconds();
                }
            }

            $days[] = [
                'date' => $dateKey,
                'day' => $cursor->day,
                'in_month' => $inMonth,
                'is_today' => $cursor->isSameDay($today),
                'is_rest_day' => $inMonth && $dayWorkouts->isEmpty(),
                'workouts' => $dayWorkouts->map(fn ($w) => [
                    'id' => $w->id,
                    'type' => $w->type,
                    'icon' => ActivityTypeIcon::icon($w->type),
                    'distance_meters' => $w->distance_meters,
                ])->values()->all(),
                'planned_workouts' => $plannedByDate->get($dateKey, collect())->map(fn ($pw) => [
                    'id' => $pw->id,
                    'type' => $pw->type,
                    'icon' => ActivityTypeIcon::icon($pw->type),
                    'duration_minutes' => $pw->duration_minutes,
                    'distance_meters' => $pw->distance_meters,
                    'intensity' => $pw->intensity,
                    'target_heart_rate_zone' => $pw->target_heart_rate_zone,
                    'warm_up' => $pw->warm_up,
                    'main_set' => $pw->main_set,
                    'cool_down' => $pw->cool_down,
                    'notes' => $pw->notes,
                    'status' => $pw->status,
                ])->values()->all(),
            ];

            $cursor->addDay();
        }

        $totalDaysInMonth = $monthStart->daysInMonth;

        return [
            'month_label' => $monthStart->translatedFormat('F Y'),
            'prev_month' => $monthStart->copy()->subMonth()->format('Y-m'),
            'next_month' => $monthStart->copy()->addMonth()->format('Y-m'),
            'days' => $days,
            'summary' => [
                'total_days' => $totalDaysInMonth,
                'active_days' => $activeDays,
                'rest_days' => $totalDaysInMonth - $activeDays,
                'total_distance_meters' => $totalDistance,
                'total_duration_seconds' => $totalDurationSeconds,
            ],
        ];
    }
}
