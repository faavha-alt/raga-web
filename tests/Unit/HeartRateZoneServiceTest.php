<?php

namespace Tests\Unit;

use App\Services\Training\HeartRateZoneService;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\TestCase;

class HeartRateZoneServiceTest extends TestCase
{
    private HeartRateZoneService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new HeartRateZoneService;
    }

    public function test_bucket_samples_weights_duration_by_time_between_samples(): void
    {
        $t0 = Carbon::parse('2026-08-13 07:00:00');

        // hr% of a 200bpm max: 50%(Z1), 65%(Z2), 75%(Z3), 85%(Z4), 95%(Z5), 50%(Z1)
        $samples = [
            ['timestamp' => $t0->copy(), 'heart_rate' => 100.0],
            ['timestamp' => $t0->copy()->addSeconds(30), 'heart_rate' => 130.0],
            ['timestamp' => $t0->copy()->addSeconds(90), 'heart_rate' => 150.0],
            ['timestamp' => $t0->copy()->addSeconds(91), 'heart_rate' => 170.0],
            ['timestamp' => $t0->copy()->addSeconds(151), 'heart_rate' => 190.0],
            ['timestamp' => $t0->copy()->addSeconds(215), 'heart_rate' => 100.0],
        ];

        $result = $this->service->bucketSamples($samples, 200);

        // Duration is attributed to the zone of the *earlier* sample in each pair.
        $this->assertSame(30, $result[1]); // [0]->[1], 30s gap, hr=100 (Z1)
        $this->assertSame(60, $result[2]); // [1]->[2], 60s gap, hr=130 (Z2)
        $this->assertSame(1, $result[3]);  // [2]->[3], 1s gap, hr=150 (Z3)
        $this->assertSame(60, $result[4]); // [3]->[4], 60s gap, hr=170 (Z4)
        $this->assertSame(0, $result[5]);  // [4]->[5] gap is 64s, over the 60s cap, skipped entirely
    }

    public function test_bucket_samples_skips_gaps_over_sixty_seconds(): void
    {
        $t0 = Carbon::parse('2026-08-13 07:00:00');

        $samples = [
            ['timestamp' => $t0->copy(), 'heart_rate' => 100.0],
            ['timestamp' => $t0->copy()->addSeconds(61), 'heart_rate' => 100.0],
        ];

        $result = $this->service->bucketSamples($samples, 200);

        $this->assertSame(0, array_sum($result));
    }

    public function test_bucket_samples_returns_all_zero_for_fewer_than_two_samples(): void
    {
        $this->assertSame([1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0], $this->service->bucketSamples([], 200));
        $this->assertSame(
            [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0],
            $this->service->bucketSamples([['timestamp' => Carbon::now(), 'heart_rate' => 100.0]], 200)
        );
    }
}
