<?php

namespace App\Services\Social;

use App\Models\User;
use App\Models\Workout;
use App\Services\Activity\ActivityQueryService;
use Illuminate\Support\Carbon;

/**
 * Statistik profil atlet — dihitung HANYA dari aktivitas yang boleh dilihat
 * penonton (scope Workout::visibleTo), tidak pernah dari data kesehatan.
 */
class AthleteStatsService
{
    public function __construct(private ActivityQueryService $activityQuery) {}

    /**
     * @return array{
     *     total_activities: int,
     *     total_distance_meters: float,
     *     total_duration_seconds: int,
     *     total_elevation_meters: float,
     *     last_4_weeks_distance_meters: float,
     * }
     */
    public function forUser(User $athlete, ?User $viewer): array
    {
        $base = fn () => Workout::query()
            ->visibleTo($viewer)
            ->where('user_id', $athlete->id);

        $durationExpr = $this->activityQuery->durationSqlExpression();

        $row = $base()
            ->selectRaw("COUNT(*) as workout_count, SUM(distance_meters) as distance, SUM(ABS({$durationExpr})) as duration, SUM(elevation_gain_meters) as elevation")
            ->first();

        $recentDistance = $base()
            ->where('start_date', '>=', Carbon::today()->subDays(27))
            ->sum('distance_meters');

        return [
            'total_activities' => (int) ($row->workout_count ?? 0),
            'total_distance_meters' => (float) ($row->distance ?? 0),
            'total_duration_seconds' => (int) ($row->duration ?? 0),
            'total_elevation_meters' => (float) ($row->elevation ?? 0),
            'last_4_weeks_distance_meters' => (float) $recentDistance,
        ];
    }
}
