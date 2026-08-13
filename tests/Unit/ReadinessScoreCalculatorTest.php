<?php

namespace Tests\Unit;

use App\Services\Recovery\ReadinessScoreCalculator;
use PHPUnit\Framework\TestCase;

class ReadinessScoreCalculatorTest extends TestCase
{
    private ReadinessScoreCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new ReadinessScoreCalculator;
    }

    public function test_base_score_is_forty_when_every_factor_is_missing(): void
    {
        $result = $this->calculator->calculate([]);

        $this->assertSame(40, $result['score']);
        $this->assertCount(4, $result['factors']);
        foreach ($result['factors'] as $factor) {
            $this->assertTrue($factor['insufficient_data']);
        }
    }

    public function test_body_battery_above_baseline_contributes_positively(): void
    {
        $result = $this->calculator->calculate([
            'body_battery' => $this->factor(value: 60.0, mean: 30.0, stddev: 15.0),
        ]);

        $this->assertGreaterThan(0, $this->contribution($result, 'body_battery'));
    }

    public function test_recent_activity_above_baseline_reduces_readiness(): void
    {
        // Heavier-than-usual training yesterday means less freshness today.
        $result = $this->calculator->calculate([
            'recent_activity' => $this->factor(value: 90.0, mean: 50.0, stddev: 10.0),
        ]);

        $this->assertLessThan(0, $this->contribution($result, 'recent_activity'));
    }

    public function test_score_clamps_to_100_when_every_factor_is_maxed_positive(): void
    {
        $result = $this->calculator->calculate([
            'body_battery' => $this->factor(value: 500.0, mean: 30.0, stddev: 10.0),
            'recent_activity' => $this->factor(value: 0.0, mean: 50.0, stddev: 10.0),
            'hrv' => $this->factor(value: 500.0, mean: 50.0, stddev: 10.0),
            'resting_hr' => $this->factor(value: 1.0, mean: 60.0, stddev: 5.0),
        ]);

        $this->assertSame(100, $result['score']);
    }

    public function test_score_clamps_to_0_when_every_factor_is_maxed_negative(): void
    {
        $result = $this->calculator->calculate([
            'body_battery' => $this->factor(value: -500.0, mean: 30.0, stddev: 10.0),
            'recent_activity' => $this->factor(value: 500.0, mean: 50.0, stddev: 10.0),
            'hrv' => $this->factor(value: 0.0, mean: 50.0, stddev: 10.0),
            'resting_hr' => $this->factor(value: 200.0, mean: 60.0, stddev: 5.0),
        ]);

        $this->assertSame(0, $result['score']);
    }

    public function test_factor_is_insufficient_data_when_sample_count_below_seven(): void
    {
        $result = $this->calculator->calculate([
            'hrv' => $this->factor(value: 55.0, mean: 50.0, stddev: 10.0, sampleCount: 3),
        ]);

        $hrv = collect($result['factors'])->firstWhere('key', 'hrv');
        $this->assertTrue($hrv['insufficient_data']);
    }

    private function factor(float $value, float $mean, float $stddev, int $sampleCount = 30): array
    {
        return [
            'value' => $value,
            'baseline_mean' => $mean,
            'baseline_stddev' => $stddev,
            'sample_count' => $sampleCount,
        ];
    }

    private function contribution(array $result, string $key): int
    {
        return collect($result['factors'])->firstWhere('key', $key)['contribution'];
    }
}
