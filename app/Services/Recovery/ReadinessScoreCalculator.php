<?php

namespace App\Services\Recovery;

/**
 * Forward-looking: how ready the body is for today specifically. See
 * ScoreCalculator for the shared mechanism.
 */
class ReadinessScoreCalculator extends ScoreCalculator
{
    protected function factors(): array
    {
        return [
            ['key' => 'body_battery', 'label' => 'Body Battery', 'weight' => 20, 'direction' => 1],
            ['key' => 'recent_activity', 'label' => 'Recent Activity', 'weight' => 15, 'direction' => -1],
            ['key' => 'hrv', 'label' => 'HRV', 'weight' => 15, 'direction' => 1],
            ['key' => 'resting_hr', 'label' => 'Resting HR', 'weight' => 12, 'direction' => -1],
        ];
    }
}
