<?php

namespace Tests\Unit;

use App\Services\Segment\PolylineCodec;
use App\Services\Segment\SegmentMatcher;
use PHPUnit\Framework\TestCase;

class SegmentMatcherTest extends TestCase
{
    private const BASE_TIMESTAMP = 1700000000;

    private SegmentMatcher $matcher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->matcher = new SegmentMatcher;
    }

    public function test_polyline_encode_matches_the_google_reference_example(): void
    {
        $encoded = PolylineCodec::encode([
            ['lat' => 38.5, 'lng' => -120.2],
            ['lat' => 40.7, 'lng' => -120.95],
            ['lat' => 43.252, 'lng' => -126.453],
        ]);

        $this->assertSame('_p~iF~ps|U_ulLnnqC_mqNvxq`@', $encoded);

        $decoded = PolylineCodec::decode($encoded);

        $this->assertSame(38.5, $decoded[0]['lat']);
        $this->assertSame(-120.2, $decoded[0]['lng']);
        $this->assertSame(43.252, $decoded[2]['lat']);
        $this->assertSame(-126.453, $decoded[2]['lng']);
    }

    public function test_polyline_round_trips_exactly_at_precision_five(): void
    {
        $points = [
            ['lat' => -7.12345, 'lng' => 110.98765],
            ['lat' => -7.12346, 'lng' => 110.98766],
            ['lat' => -7.2, 'lng' => 110.0],
        ];

        $decoded = PolylineCodec::decode(PolylineCodec::encode($points));

        $this->assertSame(PolylineCodec::encode($points), PolylineCodec::encode($decoded));

        foreach ($points as $index => $point) {
            $this->assertEqualsWithDelta($point['lat'], $decoded[$index]['lat'], 0.00001);
            $this->assertEqualsWithDelta($point['lng'], $decoded[$index]['lng'], 0.00001);
        }
    }

    public function test_forward_pass_produces_one_match_with_exact_elapsed_seconds(): void
    {
        $samples = $this->straightTrack(10);

        $match = $this->matcher->match($this->segmentFor($samples[1], $samples[6]), $samples);

        $this->assertNotNull($match);
        $this->assertSame(1, $match['start_index']);
        $this->assertSame(6, $match['end_index']);
        $this->assertSame(50.0, $match['elapsed_seconds']);
    }

    public function test_workout_that_only_passes_the_start_is_not_matched(): void
    {
        $samples = $this->straightTrack(10);

        $segment = [
            'start_lat' => $samples[1]['lat'],
            'start_lng' => $samples[1]['lng'],
            'end_lat' => -7.6,
            'end_lng' => 110.006,
        ];

        $this->assertNull($this->matcher->match($segment, $samples));
    }

    public function test_workout_passing_endpoints_in_reverse_order_is_not_matched(): void
    {
        $samples = $this->straightTrack(10);

        $match = $this->matcher->match($this->segmentFor($samples[6], $samples[1]), $samples);

        $this->assertNull($match);
    }

    public function test_gps_glitch_jump_is_not_matched(): void
    {
        $samples = [
            ['lat' => -7.5, 'lng' => 110.0, 'timestamp' => self::BASE_TIMESTAMP],
            ['lat' => -6.5, 'lng' => 110.0, 'timestamp' => self::BASE_TIMESTAMP + 10],
            ['lat' => -7.5, 'lng' => 110.001, 'timestamp' => self::BASE_TIMESTAMP + 20],
        ];

        $segment = $this->segmentFor($samples[0], $samples[2]);

        $this->assertNull($this->matcher->match($segment, $samples));
    }

    public function test_endpoint_timestamps_are_interpolated_for_precision(): void
    {
        $samples = $this->straightTrack(10);

        // Titik target berada tepat di tengah antara dua sampel (≈55 m dari
        // masing-masing), jadi hanya interpolasi yang bisa menghasilkan 30 s.
        $segment = [
            'start_lat' => -7.5,
            'start_lng' => 110.0015,
            'end_lat' => -7.5,
            'end_lng' => 110.0045,
        ];

        $match = $this->matcher->match($segment, $samples, 70.0);

        $this->assertNotNull($match);
        $this->assertSame(30.0, $match['elapsed_seconds']);
    }

    public function test_activity_type_compatibility_uses_substring_families(): void
    {
        $this->assertTrue(SegmentMatcher::typesCompatible('running', 'trail_running'));
        $this->assertTrue(SegmentMatcher::typesCompatible('cycling', 'mountain_biking'));
        $this->assertTrue(SegmentMatcher::typesCompatible('walking', 'running'));

        $this->assertFalse(SegmentMatcher::typesCompatible('running', 'cycling'));
        $this->assertFalse(SegmentMatcher::typesCompatible('running', 'swimming'));
        $this->assertFalse(SegmentMatcher::typesCompatible('hiking', 'yoga'));
    }

    /**
     * Lintasan lurus ke arah timur: sampel ke-i berjarak 0.001° (~110 m)
     * dengan selisih waktu 10 detik.
     *
     * @return list<array{lat: float, lng: float, timestamp: int}>
     */
    private function straightTrack(int $count): array
    {
        $samples = [];

        for ($i = 0; $i < $count; $i++) {
            $samples[] = [
                'lat' => -7.5,
                'lng' => 110.0 + ($i * 0.001),
                'timestamp' => self::BASE_TIMESTAMP + ($i * 10),
            ];
        }

        return $samples;
    }

    /**
     * @param  array{lat: float, lng: float}  $start
     * @param  array{lat: float, lng: float}  $end
     * @return array{start_lat: float, start_lng: float, end_lat: float, end_lng: float}
     */
    private function segmentFor(array $start, array $end): array
    {
        return [
            'start_lat' => $start['lat'],
            'start_lng' => $start['lng'],
            'end_lat' => $end['lat'],
            'end_lng' => $end['lng'],
        ];
    }
}
