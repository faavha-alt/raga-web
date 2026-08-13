<?php

namespace App\Services\Training;

use Illuminate\Support\Carbon;

/**
 * Pure (no DB) acute:chronic workload ratio (ACWR) and monotony calculator —
 * classic Foster/Banister sports-science heuristics computed from a
 * zero-filled daily training_load series. This is a personal training-load
 * indicator, not medical advice.
 */
class AcuteChronicLoadCalculator
{
    public const ACUTE_WINDOW_DAYS = 7;

    public const CHRONIC_WINDOW_DAYS = 28;

    /**
     * @param  array<string, float>  $dailyLoadByDate  Y-m-d => summed training_load that day, zero-filled for gap days.
     * @return array{
     *     acute_load: float,
     *     chronic_load: float,
     *     acute_chronic_ratio: ?float,
     *     monotony: ?float,
     *     risk_level: string,
     * }
     */
    public function calculate(array $dailyLoadByDate, Carbon $asOf): array
    {
        $acuteValues = $this->valuesForWindow($dailyLoadByDate, $asOf, self::ACUTE_WINDOW_DAYS);
        $chronicValues = $this->valuesForWindow($dailyLoadByDate, $asOf, self::CHRONIC_WINDOW_DAYS);

        $acuteLoad = $this->mean($acuteValues);
        $chronicLoad = $this->mean($chronicValues);

        // null (not 0) signals "not computable" so the view can show
        // "insufficient data" instead of a misleading ratio of 0.
        $ratio = $chronicLoad > 0.0 ? round($acuteLoad / $chronicLoad, 2) : null;

        return [
            'acute_load' => round($acuteLoad, 1),
            'chronic_load' => round($chronicLoad, 1),
            'acute_chronic_ratio' => $ratio,
            'monotony' => $this->monotony($acuteValues),
            'risk_level' => $this->riskLevelFor($ratio),
        ];
    }

    public function riskLevelFor(?float $ratio): string
    {
        return match (true) {
            $ratio === null => 'insufficient_data',
            $ratio < 0.8 => 'undertraining',
            $ratio < 1.3 => 'optimal',
            $ratio < 1.5 => 'caution',
            default => 'high_risk',
        };
    }

    /** @return list<float> */
    private function valuesForWindow(array $dailyLoadByDate, Carbon $asOf, int $days): array
    {
        $values = [];

        for ($i = 0; $i < $days; $i++) {
            $date = $asOf->copy()->subDays($i)->toDateString();
            $values[] = $dailyLoadByDate[$date] ?? 0.0;
        }

        return $values;
    }

    /** @param list<float> $values */
    private function mean(array $values): float
    {
        return $values === [] ? 0.0 : array_sum($values) / count($values);
    }

    /**
     * Foster monotony = mean(load) / stddev(load) over the acute window.
     * Zero-stddev (identical daily loads) is guarded and returns null
     * rather than an infinite/NAN value.
     *
     * @param  list<float>  $values
     */
    private function monotony(array $values): ?float
    {
        if ($values === []) {
            return null;
        }

        $mean = $this->mean($values);
        $variance = array_sum(array_map(fn ($v) => ($v - $mean) ** 2, $values)) / count($values);
        $stddev = sqrt($variance);

        return $stddev > 0.0 ? round($mean / $stddev, 2) : null;
    }
}
