<?php

namespace Tests\Unit;

use App\Services\Trail\TrailMovingTimeCalculator;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\TestCase;

class TrailMovingTimeCalculatorTest extends TestCase
{
    private TrailMovingTimeCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new TrailMovingTimeCalculator;
    }

    public function test_moving_seconds_sums_gaps_within_threshold_and_skips_larger_gaps(): void
    {
        $t0 = Carbon::parse('2026-08-13 07:00:00');

        $samples = [
            ['timestamp' => $t0->copy()],
            ['timestamp' => $t0->copy()->addSeconds(30)],  // +30s gap
            ['timestamp' => $t0->copy()->addSeconds(90)],  // +60s gap
            ['timestamp' => $t0->copy()->addSeconds(150)], // +60s gap
            ['timestamp' => $t0->copy()->addSeconds(214)], // +64s gap, excluded
        ];

        $this->assertSame(150, $this->calculator->movingSeconds($samples));
    }

    public function test_moving_seconds_includes_a_gap_of_exactly_sixty_seconds(): void
    {
        $t0 = Carbon::parse('2026-08-13 07:00:00');
        $samples = [['timestamp' => $t0->copy()], ['timestamp' => $t0->copy()->addSeconds(60)]];

        $this->assertSame(60, $this->calculator->movingSeconds($samples));
    }

    public function test_moving_seconds_excludes_a_gap_over_sixty_seconds(): void
    {
        $t0 = Carbon::parse('2026-08-13 07:00:00');
        $samples = [['timestamp' => $t0->copy()], ['timestamp' => $t0->copy()->addSeconds(61)]];

        $this->assertSame(0, $this->calculator->movingSeconds($samples));
    }

    public function test_moving_seconds_is_zero_for_fewer_than_two_samples(): void
    {
        $this->assertSame(0, $this->calculator->movingSeconds([]));
        $this->assertSame(0, $this->calculator->movingSeconds([['timestamp' => Carbon::now()]]));
    }
}
