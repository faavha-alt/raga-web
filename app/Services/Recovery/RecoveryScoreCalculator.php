<?php

namespace App\Services\Recovery;

/**
 * Backward-looking: how well did the body recover overnight/from recent
 * training. See ScoreCalculator for the shared mechanism.
 */
class RecoveryScoreCalculator extends ScoreCalculator
{
    protected function factors(): array
    {
        return [
            ['key' => 'sleep', 'label' => 'Sleep', 'weight' => 20, 'direction' => 1],
            ['key' => 'hrv', 'label' => 'HRV', 'weight' => 15, 'direction' => 1],
            ['key' => 'resting_hr', 'label' => 'Resting HR', 'weight' => 12, 'direction' => -1],
            ['key' => 'stress', 'label' => 'Stress', 'weight' => 8, 'direction' => -1],
            ['key' => 'training_load', 'label' => 'Training Load', 'weight' => 10, 'direction' => -1, 'cap_positive_at_zero' => true],
        ];
    }
}
