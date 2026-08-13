<?php

namespace App\Services\Running;

use App\Models\User;
use App\Services\Activity\ActivityQueryService;
use Illuminate\Support\Carbon;

/**
 * Weekly/monthly/yearly running-only series — distance, duration, pace,
 * average HR — shaped exactly like TrainingVolumeSeriesService (and
 * MetricSeriesService before it) so <x-health-trend-chart> works
 * unmodified. Scoped strictly to type='running' so it never mixes in
 * walking, cycling, or trail_running data.
 */
class RunningSeriesService
{
    private const TYPE = 'running';

    private const METRICS = [
        'distance' => ['label' => 'Distance', 'unit' => 'km', 'color' => '#2a78d6', 'decimals' => 1],
        'duration' => ['label' => 'Duration', 'unit' => 'min', 'color' => '#1baf7a', 'decimals' => 0],
        'pace' => ['label' => 'Pace', 'unit' => 'min/km', 'color' => '#eb6834', 'decimals' => 2],
        'avg_hr' => ['label' => 'Average HR', 'unit' => 'bpm', 'color' => '#e34948', 'decimals' => 0],
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
            'pace' => $this->dailySeries($user, $since, 'AVG(average_pace_seconds_per_km)', fn ($v) => $v / 60),
            'avg_hr' => $this->dailySeries($user, $since, 'AVG(average_heart_rate)'),
            default => [],
        };
    }

    /**
     * @return array{
     *     distance_meters: float, duration_seconds: int, activity_count: int,
     *     average_pace_seconds_per_km: ?float, average_heart_rate: ?float,
     * }
     */
    public function totalsForRange(User $user, Carbon $from, Carbon $to): array
    {
        $durationExpr = $this->activityQuery->durationSqlExpression();

        $row = $user->workouts()
            ->where('type', self::TYPE)
            ->whereBetween('start_date', [$from, $to->copy()->endOfDay()])
            ->selectRaw("SUM(distance_meters) as distance, COUNT(*) as workout_count, SUM(ABS({$durationExpr})) as duration, AVG(average_pace_seconds_per_km) as pace, AVG(average_heart_rate) as hr")
            ->first();

        return [
            'distance_meters' => (float) ($row->distance ?? 0),
            'duration_seconds' => (int) ($row->duration ?? 0),
            'activity_count' => (int) ($row->workout_count ?? 0),
            'average_pace_seconds_per_km' => $row->pace !== null ? (float) $row->pace : null,
            'average_heart_rate' => $row->hr !== null ? (float) $row->hr : null,
        ];
    }

    /**
     * DATE()/SUM()/AVG()/COUNT()/GROUP BY are portable — safe on both MySQL
     * (prod) and SQLite (tests). Days where the aggregate is NULL (e.g. no
     * workout that day recorded pace) are dropped rather than plotted as 0.
     */
    private function dailySeries(User $user, Carbon $since, string $aggregateExpr, ?callable $transform = null): array
    {
        $rows = $user->workouts()
            ->where('type', self::TYPE)
            ->where('start_date', '>=', $since)
            ->selectRaw("DATE(start_date) as workout_date, {$aggregateExpr} as agg_value")
            ->groupBy('workout_date')
            ->orderBy('workout_date')
            ->get()
            ->filter(fn ($row) => $row->agg_value !== null)
            ->values();

        return $rows->map(fn ($row) => [
            'date' => $row->workout_date,
            'value' => $transform ? $transform((float) $row->agg_value) : (float) $row->agg_value,
        ])->all();
    }
}
