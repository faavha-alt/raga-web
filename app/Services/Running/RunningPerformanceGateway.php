<?php

namespace App\Services\Running;

use App\Models\User;
use App\Services\Training\TrainingConsistencyService;
use Illuminate\Support\Carbon;

/**
 * DB-touching half of the Running Performance Score — pulls pace/VO2max
 * mean+stddev over the trailing 30 days of running workouts, plus the
 * running-scoped consistency percent, and shapes it for
 * RunningPerformanceCalculator. Computes its own mean/stddev locally (same
 * population-variance formula as PersonalBaselineService) rather than
 * going through the Health module, since these are Running-specific data
 * pulls, not generic Health metrics.
 */
class RunningPerformanceGateway
{
    private const WINDOW_DAYS = 30;

    public function __construct(private TrainingConsistencyService $consistency) {}

    public function runsInWindow(User $user, Carbon $asOf): int
    {
        return $user->workouts()
            ->where('type', 'running')
            ->whereBetween('start_date', [$this->windowStart($asOf), $asOf->copy()->endOfDay()])
            ->count();
    }

    /** @return array{value: ?float, mean: ?float, stddev: ?float} */
    public function paceInput(User $user, Carbon $asOf): array
    {
        return $this->statsFor($this->runningColumnValues($user, $asOf, 'average_pace_seconds_per_km'));
    }

    /** @return array{value: ?float, mean: ?float, stddev: ?float} */
    public function vo2maxInput(User $user, Carbon $asOf): array
    {
        $values = $user->vitalMeasurements()
            ->where('type', 'vo2max')
            ->whereBetween('date', [$this->windowStart($asOf), $asOf->copy()->endOfDay()])
            ->orderBy('date')
            ->pluck('value')
            ->map(fn ($v) => (float) $v)
            ->all();

        return $this->statsFor($values);
    }

    public function consistencyPercent(User $user, Carbon $asOf): float
    {
        $result = $this->consistency->forPeriod(
            $user,
            $this->windowStart($asOf),
            $asOf,
            type: 'running',
        );

        return $result['consistency_percent'];
    }

    private function windowStart(Carbon $asOf): Carbon
    {
        return $asOf->copy()->subDays(self::WINDOW_DAYS - 1)->startOfDay();
    }

    /** @return list<float> */
    private function runningColumnValues(User $user, Carbon $asOf, string $column): array
    {
        return $user->workouts()
            ->where('type', 'running')
            ->whereBetween('start_date', [$this->windowStart($asOf), $asOf->copy()->endOfDay()])
            ->whereNotNull($column)
            ->orderBy('start_date')
            ->pluck($column)
            ->map(fn ($v) => (float) $v)
            ->all();
    }

    /**
     * @param  list<float>  $values
     * @return array{value: ?float, mean: ?float, stddev: ?float}
     */
    private function statsFor(array $values): array
    {
        if ($values === []) {
            return ['value' => null, 'mean' => null, 'stddev' => null];
        }

        $count = count($values);
        $mean = array_sum($values) / $count;
        $variance = array_sum(array_map(fn ($v) => ($v - $mean) ** 2, $values)) / $count;

        return [
            'value' => end($values),
            'mean' => $mean,
            'stddev' => sqrt($variance),
        ];
    }
}
