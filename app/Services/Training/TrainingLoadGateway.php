<?php

namespace App\Services\Training;

use App\Models\User;
use App\Services\Activity\ActivityQueryService;
use Illuminate\Support\Carbon;

/**
 * The only piece of the Training Status engine that touches real data —
 * pulls the raw daily training_load series and weekly volume figures the
 * pure AcuteChronicLoadCalculator needs. Mirrors RecoveryFactorGateway's
 * split between DB access and pure calculation.
 */
class TrainingLoadGateway
{
    public function __construct(private ActivityQueryService $activityQuery) {}

    /**
     * Sparse map of Y-m-d => summed training_load for that day, spanning
     * $days back from $asOf (inclusive). Days with no workouts are simply
     * absent — AcuteChronicLoadCalculator treats missing days as 0.
     *
     * @return array<string, float>
     */
    public function dailyLoadSeries(User $user, Carbon $asOf, int $days): array
    {
        $since = $asOf->copy()->subDays($days - 1)->startOfDay();

        return $user->workouts()
            ->where('start_date', '>=', $since)
            ->where('start_date', '<=', $asOf->copy()->endOfDay())
            ->whereNotNull('training_load')
            ->selectRaw('DATE(start_date) as workout_date, SUM(training_load) as total_load')
            ->groupBy('workout_date')
            ->pluck('total_load', 'workout_date')
            ->map(fn ($value) => (float) $value)
            ->all();
    }

    public function weeklyDistanceMeters(User $user, Carbon $weekStart, Carbon $weekEnd): float
    {
        return (float) $user->workouts()
            ->whereBetween('start_date', [$weekStart, $weekEnd])
            ->sum('distance_meters');
    }

    /** Uses ActivityQueryService::durationSqlExpression() — never a raw TIMESTAMPDIFF() here. */
    public function weeklyDurationMinutes(User $user, Carbon $weekStart, Carbon $weekEnd): float
    {
        $durationExpr = $this->activityQuery->durationSqlExpression();

        $seconds = $user->workouts()
            ->whereBetween('start_date', [$weekStart, $weekEnd])
            ->selectRaw("SUM(ABS({$durationExpr})) as total_seconds")
            ->value('total_seconds');

        return round(($seconds ?? 0) / 60, 1);
    }

    public function trainingFrequency(User $user, Carbon $weekStart, Carbon $weekEnd): int
    {
        return (int) $user->workouts()
            ->whereBetween('start_date', [$weekStart, $weekEnd])
            ->selectRaw('COUNT(DISTINCT DATE(start_date)) as freq')
            ->value('freq');
    }
}
