<?php

namespace Tests\Unit;

use App\Services\Running\RunningPerformanceCalculator;
use PHPUnit\Framework\TestCase;

class RunningPerformanceCalculatorTest extends TestCase
{
    private RunningPerformanceCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new RunningPerformanceCalculator;
    }

    public function test_returns_null_when_fewer_than_seven_runs_in_window(): void
    {
        $result = $this->calculator->calculate(
            runsInWindow: 6,
            pace: ['value' => 280.0, 'mean' => 300.0, 'stddev' => 10.0],
            vo2max: ['value' => null, 'mean' => null, 'stddev' => null],
            consistencyPercent: 80.0,
        );

        $this->assertNull($result);
    }

    public function test_computes_a_score_at_exactly_seven_runs(): void
    {
        $result = $this->calculator->calculate(
            runsInWindow: 7,
            pace: ['value' => 280.0, 'mean' => 300.0, 'stddev' => 10.0],
            vo2max: ['value' => null, 'mean' => null, 'stddev' => null],
            consistencyPercent: 80.0,
        );

        $this->assertNotNull($result);
    }

    public function test_reproduces_a_hand_computed_worked_example(): void
    {
        // pace z=(280-300)/10=-2 clamped to -1, direction -1 (faster=better) -> +20
        // vo2max z=(50-45)/5=1 clamped to 1, direction +1 -> +15
        // consistency (80-50)/50=0.6 -> round(15*0.6)=+9
        // score = 40 + 20 + 15 + 9 = 84
        $result = $this->calculator->calculate(
            runsInWindow: 7,
            pace: ['value' => 280.0, 'mean' => 300.0, 'stddev' => 10.0],
            vo2max: ['value' => 50.0, 'mean' => 45.0, 'stddev' => 5.0],
            consistencyPercent: 80.0,
        );

        $this->assertSame(84, $result['score']);

        $byKey = collect($result['factors'])->keyBy('key');
        $this->assertSame(20, $byKey['pace']['contribution']);
        $this->assertSame(15, $byKey['vo2max']['contribution']);
        $this->assertSame(9, $byKey['consistency']['contribution']);
    }

    public function test_vo2max_factor_contributes_zero_without_failing_the_score(): void
    {
        $result = $this->calculator->calculate(
            runsInWindow: 7,
            pace: ['value' => 300.0, 'mean' => 300.0, 'stddev' => 10.0],
            vo2max: ['value' => null, 'mean' => null, 'stddev' => null],
            consistencyPercent: 50.0,
        );

        $this->assertNotNull($result);

        $vo2max = collect($result['factors'])->firstWhere('key', 'vo2max');
        $this->assertTrue($vo2max['insufficient_data']);
        $this->assertSame(0, $vo2max['contribution']);
        $this->assertSame(40, $result['score']); // pace z=0, consistency 50% normalized=0 -> base only
    }

    public function test_pace_contribution_clamps_at_max_weight_for_extreme_improvement(): void
    {
        $result = $this->calculator->calculate(
            runsInWindow: 7,
            pace: ['value' => 100.0, 'mean' => 300.0, 'stddev' => 10.0],
            vo2max: ['value' => null, 'mean' => null, 'stddev' => null],
            consistencyPercent: 50.0,
        );

        $pace = collect($result['factors'])->firstWhere('key', 'pace');
        $this->assertSame(20, $pace['contribution']);
    }

    public function test_pace_insufficient_data_when_no_baseline_exists(): void
    {
        $result = $this->calculator->calculate(
            runsInWindow: 7,
            pace: ['value' => null, 'mean' => null, 'stddev' => null],
            vo2max: ['value' => null, 'mean' => null, 'stddev' => null],
            consistencyPercent: 50.0,
        );

        $pace = collect($result['factors'])->firstWhere('key', 'pace');
        $this->assertTrue($pace['insufficient_data']);
        $this->assertSame(0, $pace['contribution']);
    }

    public function test_consistency_contribution_direction_and_clamping(): void
    {
        $zero = $this->calculator->calculate(7, ['value' => null, 'mean' => null, 'stddev' => null], ['value' => null, 'mean' => null, 'stddev' => null], 0.0);
        $full = $this->calculator->calculate(7, ['value' => null, 'mean' => null, 'stddev' => null], ['value' => null, 'mean' => null, 'stddev' => null], 100.0);

        $this->assertSame(-15, collect($zero['factors'])->firstWhere('key', 'consistency')['contribution']);
        $this->assertSame(15, collect($full['factors'])->firstWhere('key', 'consistency')['contribution']);
    }

    public function test_score_clamps_to_zero_and_one_hundred(): void
    {
        $worst = $this->calculator->calculate(7, ['value' => 500.0, 'mean' => 300.0, 'stddev' => 10.0], ['value' => 20.0, 'mean' => 50.0, 'stddev' => 5.0], 0.0);
        $best = $this->calculator->calculate(7, ['value' => 100.0, 'mean' => 300.0, 'stddev' => 10.0], ['value' => 80.0, 'mean' => 50.0, 'stddev' => 5.0], 100.0);

        $this->assertGreaterThanOrEqual(0, $worst['score']);
        $this->assertLessThanOrEqual(100, $best['score']);
    }
}
