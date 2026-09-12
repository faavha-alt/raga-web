<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Suunto\SuuntoWorkoutMapper;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Unit test murni (tanpa database/network) untuk SuuntoWorkoutMapper.
 */
class SuuntoWorkoutMapperTest extends TestCase
{
    /** Epoch milidetik 2026-09-13T00:00:00Z. */
    private const START_MS = 1_789_257_600_000;

    private SuuntoWorkoutMapper $mapper;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.timezone' => 'Asia/Jakarta']);
        $this->mapper = new SuuntoWorkoutMapper;
    }

    public function test_maps_full_workout_summary(): void
    {
        $result = $this->mapper->toGarminActivity([
            'workoutKey' => 'abc-123',
            'startTime' => '2026-09-13T05:30:00+02:00',
            'duration' => 3600,
            'distance' => 12000.0,
            'calories' => 850,
            'averageHR' => 150,
            'maxHR' => 182,
            'averageSpeed' => 3.4,
            'elevationGain' => 210.5,
            'elevationLoss' => 190.0,
            'sport' => 'TrailRunning',
            'activityName' => 'Sesi Trail Pagi',
            'aerobicTrainingEffect' => 3.4,
            'anaerobicTrainingEffect' => 1.1,
            'trainingEffectLabel' => 'IMPROVING',
            'activityTrainingLoad' => 155.0,
        ]);

        $this->assertSame('2026-09-13 10:30:00', $result['startTimeLocal']);
        $this->assertSame(3600, $result['duration']);
        $this->assertSame(12000.0, $result['distance']);
        $this->assertSame(850.0, $result['calories']);
        $this->assertSame(150, $result['averageHR']);
        $this->assertSame(182, $result['maxHR']);
        $this->assertSame(3.4, $result['averageSpeed']);
        $this->assertSame(210.5, $result['elevationGain']);
        $this->assertSame(190.0, $result['elevationLoss']);
        $this->assertSame(['typeKey' => 'trail_running'], $result['activityType']);
        $this->assertSame('Sesi Trail Pagi', $result['activityName']);
        $this->assertSame(3.4, $result['aerobicTrainingEffect']);
        $this->assertSame(1.1, $result['anaerobicTrainingEffect']);
        $this->assertSame('IMPROVING', $result['trainingEffectLabel']);
        $this->assertSame(155.0, $result['activityTrainingLoad']);
    }

    public function test_maps_alternative_field_names(): void
    {
        $result = $this->mapper->toGarminActivity([
            'key' => 'alt-1',
            'activityStartTime' => '2026-09-13T00:00:00Z',
            'totalDuration' => 1800,
            'totalDistance' => 6000.0,
            'totalCalories' => 400,
            'avgHeartRate' => 140,
            'maxHeartRate' => 170,
            'avgSpeed' => 3.333,
            'totalAscent' => 80,
            'totalDescent' => 75,
            'activityType' => ['typeKey' => 'Road Cycling'],
            'name' => 'Ride Sore',
        ]);

        $this->assertSame(1800, $result['duration']);
        $this->assertSame(6000.0, $result['distance']);
        $this->assertSame(400.0, $result['calories']);
        $this->assertSame(140, $result['averageHR']);
        $this->assertSame(170, $result['maxHR']);
        $this->assertSame(3.333, $result['averageSpeed']);
        $this->assertSame(80.0, $result['elevationGain']);
        $this->assertSame(75.0, $result['elevationLoss']);
        $this->assertSame(['typeKey' => 'road_biking'], $result['activityType']);
        $this->assertSame('Ride Sore', $result['activityName']);
    }

    public function test_converts_iso_utc_start_to_app_timezone(): void
    {
        $result = $this->mapper->toGarminActivity([
            'startTimeGmt' => '2026-09-13T00:00:00Z',
            'duration' => 600,
        ]);

        $this->assertSame('2026-09-13 07:00:00', $result['startTimeLocal']);
    }

    public function test_converts_epoch_seconds_and_milliseconds(): void
    {
        $seconds = $this->mapper->toGarminActivity([
            'startTime' => 1_789_257_600,
            'duration' => 600,
        ]);
        $milliseconds = $this->mapper->toGarminActivity([
            'startTime' => self::START_MS,
            'duration' => 600,
        ]);

        $this->assertSame('2026-09-13 07:00:00', $seconds['startTimeLocal']);
        $this->assertSame('2026-09-13 07:00:00', $milliseconds['startTimeLocal']);
    }

    public function test_builds_details_from_streams_shape(): void
    {
        $result = $this->mapper->toGarminActivity([
            'startTime' => self::START_MS,
            'duration' => 300,
            'streams' => [
                [
                    'type' => 'HeartrateStreamExtension',
                    'data' => [
                        ['timestamp' => 0, 'value' => 120],
                        ['timestamp' => 1, 'value' => 132],
                    ],
                ],
                [
                    'type' => 'SpeedStreamExtension',
                    'data' => [
                        ['timestamp' => 0, 'value' => 3.5],
                        ['timestamp' => 1, 'value' => 3.8],
                    ],
                ],
            ],
        ]);

        $details = $result['details'];
        $this->assertNotNull($details);
        $this->assertSame([
            ['key' => 'directTimestamp', 'metricsIndex' => 0],
            ['key' => 'directHeartRate', 'metricsIndex' => 1],
            ['key' => 'directSpeed', 'metricsIndex' => 2],
        ], $details['metricDescriptors']);
        $this->assertSame([self::START_MS, 120.0, 3.5], $details['activityDetailMetrics'][0]['metrics']);
        $this->assertSame([self::START_MS + 1000, 132.0, 3.8], $details['activityDetailMetrics'][1]['metrics']);
    }

    public function test_builds_details_from_extensions_shape(): void
    {
        $result = $this->mapper->toGarminActivity([
            'startTime' => self::START_MS,
            'duration' => 300,
            'extensions' => [
                'HeartrateStreamExtension' => [
                    'samples' => [
                        ['time' => 1_789_257_600, 'heartRate' => 145],
                        ['time' => 1_789_257_601, 'heartRate' => 150],
                    ],
                ],
                'AltitudeStreamExtension' => [55.0, 57.0],
                'TimeStreamExtension' => [1_789_257_600, 1_789_257_601],
            ],
        ]);

        $details = $result['details'];
        $this->assertNotNull($details);
        $this->assertSame([
            ['key' => 'directTimestamp', 'metricsIndex' => 0],
            ['key' => 'directHeartRate', 'metricsIndex' => 1],
            ['key' => 'directElevation', 'metricsIndex' => 2],
        ], $details['metricDescriptors']);
        $this->assertSame([self::START_MS, 145.0, 55.0], $details['activityDetailMetrics'][0]['metrics']);
    }

    public function test_builds_details_from_per_sample_shape(): void
    {
        $result = $this->mapper->toGarminActivity([
            'startTime' => self::START_MS,
            'duration' => 300,
            'samples' => [
                [
                    'timestamp' => 1_789_257_600,
                    'heartRate' => 100,
                    'speed' => 2.5,
                    'altitude' => 50,
                    'cadence' => 80,
                    'latitude' => -7.56,
                    'longitude' => 110.85,
                ],
                [
                    'timestamp' => 1_789_257_601,
                    'hr' => 101,
                ],
            ],
        ]);

        $details = $result['details'];
        $this->assertNotNull($details);
        $this->assertSame([
            ['key' => 'directTimestamp', 'metricsIndex' => 0],
            ['key' => 'directHeartRate', 'metricsIndex' => 1],
            ['key' => 'directSpeed', 'metricsIndex' => 2],
            ['key' => 'directElevation', 'metricsIndex' => 3],
            ['key' => 'directDoubleCadence', 'metricsIndex' => 4],
            ['key' => 'directLatitude', 'metricsIndex' => 5],
            ['key' => 'directLongitude', 'metricsIndex' => 6],
        ], $details['metricDescriptors']);
        $this->assertSame(
            [self::START_MS, 100.0, 2.5, 50.0, 80.0, -7.56, 110.85],
            $details['activityDetailMetrics'][0]['metrics']
        );
    }

    public function test_maps_laps_from_list(): void
    {
        $result = $this->mapper->toGarminActivity([
            'startTime' => self::START_MS,
            'duration' => 600,
            'laps' => [
                [
                    'lapIndex' => 1,
                    'startTimeGMT' => '2026-09-13T00:00:00Z',
                    'distance' => 1000,
                    'duration' => 300,
                    'elevationGain' => 10,
                    'elevationLoss' => 8,
                    'averageHR' => 150,
                    'maxHR' => 165,
                    'averageSpeed' => 3.33,
                    'calories' => 100,
                ],
            ],
        ]);

        $this->assertNotNull($result['laps']);
        $lap = $result['laps']['lapDTOs'][0];
        $this->assertSame(1, $lap['lapIndex']);
        $this->assertSame('2026-09-13T00:00:00+00:00', $lap['startTimeGMT']);
        $this->assertSame(1000.0, $lap['distance']);
        $this->assertSame(300, $lap['duration']);
        $this->assertSame(10.0, $lap['elevationGain']);
        $this->assertSame(8.0, $lap['elevationLoss']);
        $this->assertSame(150, $lap['averageHR']);
        $this->assertSame(165, $lap['maxHR']);
        $this->assertSame(3.33, $lap['averageSpeed']);
        $this->assertSame(100.0, $lap['calories']);
    }

    public function test_empty_workout_returns_nulls_not_zero(): void
    {
        $result = $this->mapper->toGarminActivity([]);

        $this->assertNull($result['startTimeLocal']);
        $this->assertNull($result['duration']);
        $this->assertNull($result['distance']);
        $this->assertNull($result['calories']);
        $this->assertNull($result['averageHR']);
        $this->assertNull($result['maxHR']);
        $this->assertNull($result['averageSpeed']);
        $this->assertNull($result['elevationGain']);
        $this->assertNull($result['elevationLoss']);
        $this->assertSame(['typeKey' => 'unknown'], $result['activityType']);
        $this->assertNull($result['activityName']);
        $this->assertNull($result['details']);
        $this->assertNull($result['laps']);
    }

    #[DataProvider('typeKeyProvider')]
    public function test_normalizes_activity_type_key(string $input, string $expected): void
    {
        $result = $this->mapper->toGarminActivity([
            'startTime' => self::START_MS,
            'duration' => 60,
            'activityType' => $input,
        ]);

        $this->assertSame(['typeKey' => $expected], $result['activityType']);
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function typeKeyProvider(): array
    {
        return [
            'trail camel case' => ['TrailRunning', 'trail_running'],
            'open water' => ['OpenWaterSwimming', 'open_water_swimming'],
            'mtb' => ['MTB', 'mountain_biking'],
            'road cycling with space' => ['Road Cycling', 'road_biking'],
            'running' => ['Running', 'running'],
            'unknown passthrough' => ['Padel-Tennis', 'padel_tennis'],
        ];
    }

    public function test_derives_average_speed_from_distance_and_duration(): void
    {
        $result = $this->mapper->toGarminActivity([
            'startTime' => self::START_MS,
            'duration' => 1000,
            'distance' => 2500.0,
        ]);

        $this->assertSame(2.5, $result['averageSpeed']);
    }
}
