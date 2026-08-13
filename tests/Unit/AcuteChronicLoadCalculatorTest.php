<?php

namespace Tests\Unit;

use App\Services\Training\AcuteChronicLoadCalculator;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\TestCase;

class AcuteChronicLoadCalculatorTest extends TestCase
{
    private AcuteChronicLoadCalculator $calculator;

    private Carbon $asOf;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new AcuteChronicLoadCalculator;
        $this->asOf = Carbon::parse('2026-08-13');
    }

    public function test_zero_workout_input_is_insufficient_data(): void
    {
        $result = $this->calculator->calculate([], $this->asOf);

        $this->assertSame(0.0, $result['acute_load']);
        $this->assertSame(0.0, $result['chronic_load']);
        $this->assertNull($result['acute_chronic_ratio']);
        $this->assertNull($result['monotony']);
        $this->assertSame('insufficient_data', $result['risk_level']);
    }

    public function test_reproduces_a_hand_computed_worked_example(): void
    {
        // Acute window (last 7 days, most-recent-first): 90,80,70,60,50,40,30 -> mean 60, stddev 20.
        // Chronic window: those 7 days plus 21 more days at a flat 60 -> mean 60.
        $dailyLoad = [];
        foreach ([90, 80, 70, 60, 50, 40, 30] as $i => $value) {
            $dailyLoad[$this->asOf->copy()->subDays($i)->toDateString()] = (float) $value;
        }
        for ($i = 7; $i < 28; $i++) {
            $dailyLoad[$this->asOf->copy()->subDays($i)->toDateString()] = 60.0;
        }

        $result = $this->calculator->calculate($dailyLoad, $this->asOf);

        $this->assertSame(60.0, $result['acute_load']);
        $this->assertSame(60.0, $result['chronic_load']);
        $this->assertSame(1.0, $result['acute_chronic_ratio']);
        $this->assertSame(3.0, $result['monotony']);
        $this->assertSame('optimal', $result['risk_level']);
    }

    public function test_zero_stddev_monotony_is_guarded_to_null(): void
    {
        $dailyLoad = [];
        for ($i = 0; $i < 7; $i++) {
            $dailyLoad[$this->asOf->copy()->subDays($i)->toDateString()] = 40.0;
        }

        $result = $this->calculator->calculate($dailyLoad, $this->asOf);

        $this->assertNull($result['monotony']);
        $this->assertIsFloat($result['acute_chronic_ratio']);
    }

    public function test_risk_level_boundaries(): void
    {
        $this->assertSame('undertraining', $this->calculator->riskLevelFor(0.79));
        $this->assertSame('optimal', $this->calculator->riskLevelFor(0.8));
        $this->assertSame('optimal', $this->calculator->riskLevelFor(1.29));
        $this->assertSame('caution', $this->calculator->riskLevelFor(1.3));
        $this->assertSame('caution', $this->calculator->riskLevelFor(1.49));
        $this->assertSame('high_risk', $this->calculator->riskLevelFor(1.5));
        $this->assertSame('insufficient_data', $this->calculator->riskLevelFor(null));
    }
}
