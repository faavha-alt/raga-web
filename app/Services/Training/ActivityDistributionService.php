<?php

namespace App\Services\Training;

use App\Models\User;
use App\Services\Activity\ActivityQueryService;
use App\Support\ActivityTypeIcon;
use Illuminate\Support\Carbon;

class ActivityDistributionService
{
    public function __construct(private ActivityQueryService $activityQuery) {}

    /** @return list<array{type:string,label:string,icon:string,count:int,distance_meters:float,duration_seconds:int,percent:float}> */
    public function byType(User $user, Carbon $from, Carbon $to): array
    {
        $durationExpr = $this->activityQuery->durationSqlExpression();

        $rows = $user->workouts()
            ->whereBetween('start_date', [$from, $to->copy()->endOfDay()])
            ->selectRaw("type, COUNT(*) as cnt, SUM(distance_meters) as dist, SUM(ABS({$durationExpr})) as dur")
            ->groupBy('type')
            ->orderByDesc('cnt')
            ->get();

        $totalCount = (int) $rows->sum('cnt');

        return $rows->map(fn ($row) => [
            'type' => $row->type,
            'label' => ActivityTypeIcon::label($row->type),
            'icon' => ActivityTypeIcon::icon($row->type),
            'count' => (int) $row->cnt,
            'distance_meters' => (float) ($row->dist ?? 0),
            'duration_seconds' => (int) ($row->dur ?? 0),
            'percent' => $totalCount > 0 ? round($row->cnt / $totalCount * 100, 1) : 0.0,
        ])->values()->all();
    }
}
