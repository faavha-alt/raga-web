<?php

namespace App\Services\Activity;

use App\Models\User;

class ActivitySummaryService
{
    public function __construct(private ActivityQueryService $queryService) {}

    /**
     * @return array{count: int, total_distance_meters: float, total_duration_seconds: int, total_calories: float}
     */
    public function summarize(User $user, array $filters): array
    {
        $query = $this->queryService->applyFilters($user->workouts()->newQuery(), $filters);

        $count = (clone $query)->count();

        if ($count === 0) {
            return [
                'count' => 0,
                'total_distance_meters' => 0.0,
                'total_duration_seconds' => 0,
                'total_calories' => 0.0,
            ];
        }

        $totals = (clone $query)->selectRaw('
            SUM(distance_meters) as total_distance,
            SUM(active_calories) as total_calories,
            SUM(ABS(TIMESTAMPDIFF(SECOND, start_date, end_date))) as total_duration
        ')->first();

        return [
            'count' => $count,
            'total_distance_meters' => (float) ($totals->total_distance ?? 0),
            'total_duration_seconds' => (int) ($totals->total_duration ?? 0),
            'total_calories' => (float) ($totals->total_calories ?? 0),
        ];
    }
}
