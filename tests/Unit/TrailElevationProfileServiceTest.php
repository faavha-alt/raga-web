<?php

namespace Tests\Unit;

use App\Services\Trail\TrailElevationProfileService;
use PHPUnit\Framework\TestCase;

class TrailElevationProfileServiceTest extends TestCase
{
    private TrailElevationProfileService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new TrailElevationProfileService;
    }

    public function test_grade_and_distance_math_matches_known_haversine_distance(): void
    {
        // ~0.001 deg of latitude is ~111.2m (same longitude isolates the
        // haversine formula from its longitude/cosine term).
        $points = [
            ['lat' => 0.0, 'lng' => 0.0, 'altitude' => 100.0],
            ['lat' => 0.001, 'lng' => 0.0, 'altitude' => 105.56],
        ];

        $result = $this->service->buildProfile($points);

        $this->assertTrue($result['available']);
        $this->assertEqualsWithDelta(111.2, $result['total_distance_meters'], 1.0);
        $this->assertEqualsWithDelta(5.0, $result['avg_grade_percent'], 0.2);
        $this->assertEqualsWithDelta(5.0, $result['max_grade_percent'], 0.2);
        $this->assertCount(2, $result['points']);
    }

    public function test_near_zero_distance_segments_are_skipped(): void
    {
        $points = [
            ['lat' => 0.0, 'lng' => 0.0, 'altitude' => 100.0],
            ['lat' => 0.0000001, 'lng' => 0.0, 'altitude' => 100.001], // ~0.01m, under the 2m floor
            ['lat' => 0.001, 'lng' => 0.0, 'altitude' => 105.56],
        ];

        $result = $this->service->buildProfile($points);

        // Only the first->last segment counts - the near-zero middle segment
        // is skipped rather than producing a spiky grade or its own point.
        $this->assertCount(2, $result['points']);
        $this->assertEqualsWithDelta(111.2, $result['total_distance_meters'], 1.0);
    }

    public function test_fewer_than_two_points_is_unavailable(): void
    {
        $result = $this->service->buildProfile([]);
        $this->assertFalse($result['available']);
        $this->assertSame([], $result['points']);

        $result = $this->service->buildProfile([['lat' => 0.0, 'lng' => 0.0, 'altitude' => 100.0]]);
        $this->assertFalse($result['available']);
    }
}
