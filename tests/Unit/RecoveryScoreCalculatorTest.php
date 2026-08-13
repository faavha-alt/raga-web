<?php

namespace Tests\Unit;

use App\Services\Recovery\RecoveryScoreCalculator;
use PHPUnit\Framework\TestCase;

class RecoveryScoreCalculatorTest extends TestCase
{
    private RecoveryScoreCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new RecoveryScoreCalculator;
    }

    public function test_base_score_is_forty_when_every_factor_is_missing(): void
    {
        $result = $this->calculator->calculate([]);

        $this->assertSame(40, $result['score']);
        $this->assertCount(5, $result['factors']);
        foreach ($result['factors'] as $factor) {
            $this->assertTrue($factor['insufficient_data']);
            $this->assertSame(0, $factor['contribution']);
        }
    }

    public function test_reproduces_the_specification_worked_example(): void
    {
        // Sleep +18, HRV +12, Resting HR +10, Stress +8, Training -6 => 82
        $inputs = [
            'sleep' => $this->factor(value: 7.9, mean: 7.0, stddev: 1.0),
            'hrv' => $this->factor(value: 58.0, mean: 50.0, stddev: 10.0),
            'resting_hr' => $this->factor(value: 55.0, mean: 60.0, stddev: 6.0),
            'stress' => $this->factor(value: 20.0, mean: 30.0, stddev: 10.0),
            'training_load' => $this->factor(value: 56.0, mean: 50.0, stddev: 10.0),
        ];

        $result = $this->calculator->calculate($inputs);

        $this->assertSame(82, $result['score']);

        $byKey = collect($result['factors'])->keyBy('key');
        $this->assertSame(18, $byKey['sleep']['contribution']);
        $this->assertSame(12, $byKey['hrv']['contribution']);
        $this->assertSame(10, $byKey['resting_hr']['contribution']);
        $this->assertSame(8, $byKey['stress']['contribution']);
        $this->assertSame(-6, $byKey['training_load']['contribution']);
    }

    public function test_sleep_above_baseline_contributes_positively(): void
    {
        $result = $this->calculator->calculate([
            'sleep' => $this->factor(value: 8.5, mean: 7.0, stddev: 1.0),
        ]);

        $this->assertGreaterThan(0, $this->contribution($result, 'sleep'));
    }

    public function test_resting_heart_rate_above_baseline_contributes_negatively(): void
    {
        // Higher-than-usual resting HR is worse for recovery, not better.
        $result = $this->calculator->calculate([
            'resting_hr' => $this->factor(value: 70.0, mean: 60.0, stddev: 5.0),
        ]);

        $this->assertLessThan(0, $this->contribution($result, 'resting_hr'));
    }

    public function test_training_load_below_baseline_never_adds_points(): void
    {
        // Less training than usual shouldn't be rewarded as an "achievement".
        $result = $this->calculator->calculate([
            'training_load' => $this->factor(value: 10.0, mean: 50.0, stddev: 10.0),
        ]);

        $this->assertLessThanOrEqual(0, $this->contribution($result, 'training_load'));
    }

    public function test_contribution_clamps_at_max_weight_for_extreme_z_score(): void
    {
        $result = $this->calculator->calculate([
            // z would be 10 standard deviations above baseline without clamping.
            'sleep' => $this->factor(value: 20.0, mean: 7.0, stddev: 1.0),
        ]);

        $this->assertSame(20, $this->contribution($result, 'sleep'));
    }

    public function test_score_reaches_its_design_ceiling_when_every_favorable_factor_is_maxed(): void
    {
        // Training Load can only ever subtract (never rewards low training as
        // an "achievement" - see cap_positive_at_zero), so the true ceiling
        // with every other factor maxed favorably is 40 + 20 + 15 + 12 + 8 = 95,
        // not 100. This is by design, not a missing clamp.
        $result = $this->calculator->calculate([
            'sleep' => $this->factor(value: 100.0, mean: 7.0, stddev: 1.0),
            'hrv' => $this->factor(value: 500.0, mean: 50.0, stddev: 10.0),
            'resting_hr' => $this->factor(value: 1.0, mean: 60.0, stddev: 5.0),
            'stress' => $this->factor(value: 0.0, mean: 30.0, stddev: 10.0),
            'training_load' => $this->factor(value: 0.0, mean: 50.0, stddev: 10.0),
        ]);

        $this->assertSame(95, $result['score']);
        $this->assertLessThanOrEqual(100, $result['score']);
    }

    public function test_score_clamps_to_0_when_every_factor_is_maxed_negative(): void
    {
        $result = $this->calculator->calculate([
            'sleep' => $this->factor(value: 0.0, mean: 7.0, stddev: 1.0),
            'hrv' => $this->factor(value: 0.0, mean: 50.0, stddev: 10.0),
            'resting_hr' => $this->factor(value: 200.0, mean: 60.0, stddev: 5.0),
            'stress' => $this->factor(value: 100.0, mean: 30.0, stddev: 10.0),
            'training_load' => $this->factor(value: 500.0, mean: 50.0, stddev: 10.0),
        ]);

        $this->assertSame(0, $result['score']);
    }

    public function test_factor_is_insufficient_data_when_sample_count_below_seven(): void
    {
        $result = $this->calculator->calculate([
            'sleep' => $this->factor(value: 8.0, mean: 7.0, stddev: 1.0, sampleCount: 6),
        ]);

        $sleep = collect($result['factors'])->firstWhere('key', 'sleep');
        $this->assertTrue($sleep['insufficient_data']);
        $this->assertSame(0, $sleep['contribution']);
    }

    public function test_factor_is_insufficient_data_when_todays_value_is_missing(): void
    {
        $result = $this->calculator->calculate([
            'hrv' => ['value' => null, 'baseline_mean' => 50.0, 'baseline_stddev' => 10.0, 'sample_count' => 30],
        ]);

        $hrv = collect($result['factors'])->firstWhere('key', 'hrv');
        $this->assertTrue($hrv['insufficient_data']);
    }

    public function test_zero_baseline_variance_does_not_error_and_is_treated_as_neutral(): void
    {
        $result = $this->calculator->calculate([
            'hrv' => $this->factor(value: 55.0, mean: 50.0, stddev: 0.0),
        ]);

        $this->assertSame(0, $this->contribution($result, 'hrv'));
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
