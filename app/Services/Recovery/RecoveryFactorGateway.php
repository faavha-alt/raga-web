<?php

namespace App\Services\Recovery;

use App\Models\User;
use App\Services\Health\MetricSeriesService;
use App\Services\Health\PersonalBaselineService;
use Illuminate\Support\Carbon;

/**
 * The only piece of the Recovery Engine that touches real data — shapes
 * today's (or any given date's) value plus the user's personal baseline
 * into the plain array format ScoreCalculator expects. The calculators
 * themselves stay pure/DB-free so they're trivial to unit test.
 */
class RecoveryFactorGateway
{
    public function __construct(
        private MetricSeriesService $series,
        private PersonalBaselineService $baseline,
    ) {}

    /** @return array<string, array{value: ?float, baseline_mean: ?float, baseline_stddev: ?float, sample_count: int}> */
    public function recoveryInputs(User $user, Carbon $date): array
    {
        return [
            'sleep' => $this->factorInput($user, 'sleep', $date),
            'hrv' => $this->factorInput($user, 'hrv', $date),
            'resting_hr' => $this->factorInput($user, 'resting_hr', $date),
            'stress' => $this->factorInput($user, 'stress', $date),
            'training_load' => $this->factorInput($user, 'training_load', $date),
        ];
    }

    /** @return array<string, array{value: ?float, baseline_mean: ?float, baseline_stddev: ?float, sample_count: int}> */
    public function readinessInputs(User $user, Carbon $date): array
    {
        return [
            'body_battery' => $this->factorInput($user, 'body_battery_net', $date, baselineMetric: 'body_battery_net'),
            // "Recent activity" = yesterday's training load, compared against the same training-load baseline.
            'recent_activity' => $this->factorInput($user, 'training_load', $date->copy()->subDay(), baselineMetric: 'training_load'),
            'hrv' => $this->factorInput($user, 'hrv', $date),
            'resting_hr' => $this->factorInput($user, 'resting_hr', $date),
        ];
    }

    /** @return array{value: ?float, baseline_mean: ?float, baseline_stddev: ?float, sample_count: int} */
    private function factorInput(User $user, string $metricKey, Carbon $date, ?string $baselineMetric = null): array
    {
        $baseline = $this->baseline->compute($user, $baselineMetric ?? $metricKey);

        $points = $this->series->seriesFor($user, $metricKey, 30);
        $point = collect($points)->firstWhere('date', $date->toDateString());

        return [
            'value' => $point['value'] ?? null,
            'baseline_mean' => $baseline['mean'] ?? null,
            'baseline_stddev' => $baseline['stddev'] ?? null,
            'sample_count' => $baseline['sample_count'] ?? 0,
        ];
    }
}
