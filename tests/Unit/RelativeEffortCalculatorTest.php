<?php

namespace Tests\Unit;

use App\Services\Training\HeartRateZoneService;
use App\Services\Training\RelativeEffortCalculator;
use PHPUnit\Framework\TestCase;

class RelativeEffortCalculatorTest extends TestCase
{
    private RelativeEffortCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new RelativeEffortCalculator(new HeartRateZoneService);
    }

    public function test_returns_null_when_no_time_in_any_zone(): void
    {
        $this->assertNull($this->calculator->fromZoneSeconds([1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0]));
        $this->assertNull($this->calculator->fromZoneSeconds([]));
    }

    public function test_weights_each_zone_by_its_number(): void
    {
        // 60 menit di Z2 = 60 × 2 = 120.
        $this->assertSame(120, $this->calculator->fromZoneSeconds([1 => 0, 2 => 3600, 3 => 0, 4 => 0, 5 => 0]));

        // 30 menit di Z2 (60) + 10 menit di Z5 (50) = 110.
        $this->assertSame(110, $this->calculator->fromZoneSeconds([1 => 0, 2 => 1800, 3 => 0, 4 => 0, 5 => 600]));
    }

    public function test_higher_intensity_produces_higher_score_for_the_same_duration(): void
    {
        $easy = $this->calculator->fromZoneSeconds([1 => 0, 2 => 1800, 3 => 0, 4 => 0, 5 => 0]);
        $hard = $this->calculator->fromZoneSeconds([1 => 0, 2 => 0, 3 => 0, 4 => 1800, 5 => 0]);

        $this->assertSame(60, $easy);
        $this->assertSame(120, $hard);
        $this->assertGreaterThan($easy, $hard);
    }

    public function test_rounds_to_the_nearest_whole_point(): void
    {
        // 30 detik di Z3 = 0.5 menit × 3 = 1.5 → 2.
        $this->assertSame(2, $this->calculator->fromZoneSeconds([1 => 0, 2 => 0, 3 => 30, 4 => 0, 5 => 0]));
    }

    public function test_ignores_unknown_zone_keys_when_summing_total(): void
    {
        // Zona tak dikenal tetap dihitung sebagai total detik, tetapi tidak
        // menambah skor — perilaku deterministik, bukan crash.
        $this->assertSame(120, $this->calculator->fromZoneSeconds([2 => 3600, 9 => 600]));
    }
}
