<?php

namespace App\Services\Training;

use App\Models\User;
use App\Services\Activity\ActivityQueryService;
use Illuminate\Support\Carbon;

/**
 * Weekly/monthly training volume series — distance, duration, elevation
 * gain, activity count — deliberately shaped like MetricSeriesService
 * (meta()/seriesFor()) so <x-health-trend-chart> can be reused unmodified.
 */
class TrainingVolumeSeriesService
{
    private const METRICS = [
        'distance' => ['label' => 'Distance', 'unit' => 'km', 'color' => '#2a78d6', 'decimals' => 1],
        'duration' => ['label' => 'Duration', 'unit' => 'min', 'color' => '#1baf7a', 'decimals' => 0],
        'elevation_gain' => ['label' => 'Elevation Gain', 'unit' => 'm', 'color' => '#eda100', 'decimals' => 0],
        'activity_count' => ['label' => 'Activity Count', 'unit' => '', 'color' => '#4a3aa7', 'decimals' => 0],
    ];

    public function __construct(private ActivityQueryService $activityQuery) {}

    public function meta(string $metric): array
    {
        return self::METRICS[$metric] ?? ['label' => $metric, 'unit' => '', 'color' => '#2a78d6', 'decimals' => 0];
    }

    /** @return list<array{date: string, value: float}> */
    public function seriesFor(User $user, string $metric, int $days): array
    {
        $since = Carbon::today()->subDays($days - 1);

        return match ($metric) {
            'distance' => $this->dailySeries($user, $since, 'SUM(distance_meters)', fn ($v) => $v / 1000),
            'duration' => $this->dailySeries($user, $since, 'SUM(ABS('.$this->activityQuery->durationSqlExpression().'))', fn ($v) => $v / 60),
            'elevation_gain' => $this->dailySeries($user, $since, 'SUM(elevation_gain_meters)'),
            'activity_count' => $this->dailySeries($user, $since, 'COUNT(*)'),
            default => [],
        };
    }

    /** @return array{distance_meters: float, duration_seconds: int, elevation_gain_meters: float, activity_count: int} */
    public function totalsForRange(User $user, Carbon $from, Carbon $to): array
    {
        $durationExpr = $this->activityQuery->durationSqlExpression();

        $row = $user->workouts()
            ->whereBetween('start_date', [$from, $to])
            ->selectRaw("SUM(distance_meters) as distance, SUM(elevation_gain_meters) as elevation, COUNT(*) as workout_count, SUM(ABS({$durationExpr})) as duration")
            ->first();

        return [
            'distance_meters' => (float) ($row->distance ?? 0),
            'duration_seconds' => (int) ($row->duration ?? 0),
            'elevation_gain_meters' => (float) ($row->elevation ?? 0),
            'activity_count' => (int) ($row->workout_count ?? 0),
        ];
    }

    /** DATE()/SUM()/COUNT()/GROUP BY are portable — safe on both MySQL (prod) and SQLite (tests). */
    private function dailySeries(User $user, Carbon $since, string $aggregateExpr, ?callable $transform = null): array
    {
        $rows = $user->workouts()
            ->where('start_date', '>=', $since)
            ->selectRaw("DATE(start_date) as workout_date, {$aggregateExpr} as agg_value")
            ->groupBy('workout_date')
            ->orderBy('workout_date')
            ->get();

        return $rows->map(fn ($row) => [
            'date' => $row->workout_date,
            'value' => $transform ? $transform((float) $row->agg_value) : (float) $row->agg_value,
        ])->all();
    }
}
