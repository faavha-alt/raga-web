<?php

namespace App\Services\Running;

/**
 * Pure (no DB) Running Performance Score — base + bounded z-score factors,
 * same philosophy as Recovery/Readiness (App\Services\Recovery) and
 * Training Status (App\Services\Training\AcuteChronicLoadCalculator), kept
 * as its own small class rather than sharing code across domains.
 *
 *     score = clamp(40 + pace_factor + vo2max_factor + consistency_factor, 0, 100)
 *
 * This is a personal performance indicator, not a medical score. Returns
 * null entirely when there isn't enough running history (fewer than
 * MIN_RUNS runs in the trailing 30 days) rather than a fabricated number.
 */
class RunningPerformanceCalculator
{
    public const MIN_RUNS = 7;

    private const BASE_SCORE = 40;

    private const PACE_WEIGHT = 20;

    private const VO2MAX_WEIGHT = 15;

    private const CONSISTENCY_WEIGHT = 15;

    /**
     * @param  array{value: ?float, mean: ?float, stddev: ?float}  $pace  seconds/km, lower is better
     * @param  array{value: ?float, mean: ?float, stddev: ?float}  $vo2max  ml/kg/min, higher is better; pass all-null if unavailable
     * @return array{
     *     score: int,
     *     factors: list<array{key: string, label: string, contribution: int, insufficient_data: bool}>,
     * }|null
     */
    public function calculate(int $runsInWindow, array $pace, array $vo2max, float $consistencyPercent): ?array
    {
        if ($runsInWindow < self::MIN_RUNS) {
            return null;
        }

        $paceContribution = $this->zScoreContribution($pace, self::PACE_WEIGHT, direction: -1);
        $vo2maxContribution = $this->zScoreContribution($vo2max, self::VO2MAX_WEIGHT, direction: 1);
        $consistencyContribution = $this->consistencyContribution($consistencyPercent);

        $factors = [
            ['key' => 'pace', 'label' => 'Pace', 'contribution' => $paceContribution['contribution'], 'insufficient_data' => $paceContribution['insufficient_data']],
            ['key' => 'vo2max', 'label' => 'VO2 Max', 'contribution' => $vo2maxContribution['contribution'], 'insufficient_data' => $vo2maxContribution['insufficient_data']],
            ['key' => 'consistency', 'label' => 'Consistency', 'contribution' => $consistencyContribution, 'insufficient_data' => false],
        ];

        $total = array_sum(array_column($factors, 'contribution'));
        $score = (int) max(0, min(100, self::BASE_SCORE + $total));

        return ['score' => $score, 'factors' => $factors];
    }

    /**
     * @param  array{value: ?float, mean: ?float, stddev: ?float}  $input
     * @return array{contribution: int, insufficient_data: bool}
     */
    private function zScoreContribution(array $input, int $weight, int $direction): array
    {
        $value = $input['value'] ?? null;
        $mean = $input['mean'] ?? null;
        $stddev = $input['stddev'] ?? null;

        if ($value === null || $mean === null) {
            return ['contribution' => 0, 'insufficient_data' => true];
        }

        $z = ($stddev && $stddev > 0) ? ($value - $mean) / $stddev : 0.0;
        $z = max(-1.0, min(1.0, $z));

        return ['contribution' => (int) round($weight * $z * $direction), 'insufficient_data' => false];
    }

    /** Normalized percentage (not a z-score) — 100% -> +weight, 0% -> -weight, 50% -> 0. */
    private function consistencyContribution(float $percent): int
    {
        $normalized = max(-1.0, min(1.0, ($percent - 50) / 50));

        return (int) round(self::CONSISTENCY_WEIGHT * $normalized);
    }
}
