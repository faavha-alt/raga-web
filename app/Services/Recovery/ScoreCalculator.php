<?php

namespace App\Services\Recovery;

/**
 * Shared, pure (no DB) scoring mechanism for both Recovery and Readiness:
 *
 *     score = clamp(40 + sum(factor contributions), 0, 100)
 *
 * Each factor's contribution is weight * clamp(z, -1, 1) * direction, where
 * z is how many standard deviations today's value is from the user's own
 * 30-day baseline. A factor with no value today or an insufficient baseline
 * (< 7 samples, enforced upstream by PersonalBaselineService) contributes
 * exactly 0 and is flagged, never guessed.
 *
 * This is a personal performance indicator, not a medical score — 40 is a
 * neutral "average day" anchor, not a claim about what's medically normal.
 */
abstract class ScoreCalculator
{
    private const BASE_SCORE = 40;

    /**
     * @return list<array{key: string, label: string, weight: int, direction: int, cap_positive_at_zero?: bool}>
     */
    abstract protected function factors(): array;

    /**
     * @param  array<string, array{value: ?float, baseline_mean: ?float, baseline_stddev: ?float, sample_count: int}>  $inputs
     * @return array{score: int, factors: list<array{key: string, label: string, contribution: int, insufficient_data: bool}>}
     */
    public function calculate(array $inputs): array
    {
        $breakdown = [];
        $total = 0;

        foreach ($this->factors() as $spec) {
            $result = $this->scoreFactor($spec, $inputs[$spec['key']] ?? null);
            $breakdown[] = $result;
            $total += $result['contribution'];
        }

        $score = (int) max(0, min(100, self::BASE_SCORE + $total));

        return ['score' => $score, 'factors' => $breakdown];
    }

    /**
     * @param  array{key: string, label: string, weight: int, direction: int, cap_positive_at_zero?: bool}  $spec
     * @param  ?array{value: ?float, baseline_mean: ?float, baseline_stddev: ?float, sample_count: int}  $input
     * @return array{key: string, label: string, contribution: int, insufficient_data: bool}
     */
    private function scoreFactor(array $spec, ?array $input): array
    {
        $value = $input['value'] ?? null;
        $mean = $input['baseline_mean'] ?? null;
        $stddev = $input['baseline_stddev'] ?? null;
        $sampleCount = $input['sample_count'] ?? 0;

        if ($value === null || $mean === null || $sampleCount < 7) {
            return [
                'key' => $spec['key'],
                'label' => $spec['label'],
                'contribution' => 0,
                'insufficient_data' => true,
            ];
        }

        $z = ($stddev && $stddev > 0) ? ($value - $mean) / $stddev : 0.0;
        $z = max(-1.0, min(1.0, $z));

        $contribution = $spec['weight'] * $z * $spec['direction'];

        if (! empty($spec['cap_positive_at_zero'])) {
            $contribution = min(0, $contribution);
        }

        return [
            'key' => $spec['key'],
            'label' => $spec['label'],
            'contribution' => (int) round($contribution),
            'insufficient_data' => false,
        ];
    }
}
