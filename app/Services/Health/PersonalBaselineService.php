<?php

namespace App\Services\Health;

use App\Models\PersonalBaseline;
use App\Models\User;

/**
 * Computes and caches a personal mean/standard-deviation baseline per
 * metric over a rolling window, requiring a minimum sample count before
 * considering the data "sufficient" (the spec's condition). Every result
 * is explicitly a personal performance indicator, never a medical
 * reference range — callers must show the disclaimer alongside it.
 */
class PersonalBaselineService
{
    public const DISCLAIMER = 'Indikator performa personal, bukan diagnosis medis.';

    private const MIN_SAMPLES = 7;

    private const WINDOW_DAYS = 30;

    public function __construct(private MetricSeriesService $series) {}

    /**
     * @return array{mean: float, stddev: float, sample_count: int, latest: float, percent_diff: float}|null
     */
    public function compute(User $user, string $metric): ?array
    {
        $points = $this->series->seriesFor($user, $metric, self::WINDOW_DAYS);

        if (count($points) < self::MIN_SAMPLES) {
            return null;
        }

        $values = array_column($points, 'value');
        $count = count($values);
        $mean = array_sum($values) / $count;
        $variance = array_sum(array_map(fn ($v) => ($v - $mean) ** 2, $values)) / $count;
        $stddev = sqrt($variance);
        $latest = (float) end($values);

        PersonalBaseline::updateOrCreate(
            ['user_id' => $user->id, 'metric_type' => $metric, 'window_days' => self::WINDOW_DAYS],
            [
                'mean_value' => $mean,
                'standard_deviation' => $stddev,
                'sample_count' => $count,
                'calculated_at' => now(),
            ]
        );

        $percentDiff = $mean != 0.0 ? (($latest - $mean) / abs($mean)) * 100 : 0.0;

        return [
            'mean' => $mean,
            'stddev' => $stddev,
            'sample_count' => $count,
            'latest' => $latest,
            'percent_diff' => $percentDiff,
        ];
    }
}
