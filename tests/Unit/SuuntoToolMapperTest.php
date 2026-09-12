<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Suunto\SuuntoToolMapper;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Unit test murni (tanpa database/network) untuk SuuntoToolMapper.
 *
 * Bentuk JSON yang dipakai di sini diambil apa adanya dari test/go suuntool
 * (workouts_test.go, workout_detail_extra_fields.json, tools_read_test.go,
 * wellness_test.go, wellness_sleep_pretty_test.go).
 */
class SuuntoToolMapperTest extends TestCase
{
    /** Epoch milidetik 2026-09-13T00:00:00Z. */
    private const START_MS = 1_789_257_600_000;

    /** Epoch milidetik 2026-05-09T11:08:01Z. */
    private const SAMPLE_MS = 1_778_324_881_000;

    private SuuntoToolMapper $mapper;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.timezone' => 'Asia/Jakarta']);
        $this->mapper = new SuuntoToolMapper;
    }

    // ---------------------------------------------------------------------
    // toGarminActivity
    // ---------------------------------------------------------------------

    public function test_maps_full_list_workout_summary(): void
    {
        $result = $this->mapper->toGarminActivity([
            'key' => 'wk1',
            'username' => 'alice',
            'activityId' => 1,
            'activityName' => 'TRAIL_RUNNING',
            'startTime' => self::START_MS,
            'stopTime' => self::START_MS + 3_600_000,
            'totalTime' => 3600.0,
            'totalDistance' => 12000.0,
            'totalAscent' => 210.5,
            'totalDescent' => 190.0,
            'energyConsumption' => 850.0,
            'hrdata' => ['avg' => 150, 'workoutMaxHR' => 182],
            'tss' => ['trainingStressScore' => 155.0],
        ]);

        $this->assertSame('2026-09-13 07:00:00', $result['startTimeLocal']);
        $this->assertSame(3600, $result['duration']);
        $this->assertSame(12000.0, $result['distance']);
        $this->assertSame(850.0, $result['calories']);
        $this->assertSame(150, $result['averageHR']);
        $this->assertSame(182, $result['maxHR']);
        $this->assertEqualsWithDelta(3.3333, $result['averageSpeed'], 0.0001);
        $this->assertSame(210.5, $result['elevationGain']);
        $this->assertSame(190.0, $result['elevationLoss']);
        $this->assertSame(['typeKey' => 'trail_running'], $result['activityType']);
        $this->assertSame('TRAIL_RUNNING', $result['activityName']);
        $this->assertSame(155.0, $result['activityTrainingLoad']);
        $this->assertNull($result['details']);
        $this->assertNull($result['laps']);
    }

    public function test_maps_detail_workout_with_extra_fields(): void
    {
        $result = $this->mapper->toGarminActivity($this->detailFixture());

        $this->assertSame('2023-11-15 05:13:20', $result['startTimeLocal']);
        $this->assertSame(1800, $result['duration']);
        $this->assertSame(5000.0, $result['distance']);
        $this->assertSame(321.5, $result['calories']);
        $this->assertSame(145, $result['averageHR']);
        $this->assertSame(172, $result['maxHR']);
        // hrdata hanya punya `max` mentah; workoutMaxHR yang dipakai.
        $this->assertSame(['typeKey' => 'running'], $result['activityType']);
        $this->assertNull($result['activityName']);
        $this->assertSame(62.4, $result['activityTrainingLoad']);
        $this->assertNull($result['aerobicTrainingEffect']);
        $this->assertNull($result['elevationGain']);
    }

    public function test_builds_details_from_sml_samples(): void
    {
        $result = $this->mapper->toGarminActivity(
            ['startTime' => self::START_MS, 'totalTime' => 300.0, 'activityId' => 1],
            $this->smlFixture(),
        );

        $details = $result['details'];
        $this->assertNotNull($details);
        $this->assertSame([
            ['key' => 'directTimestamp', 'metricsIndex' => 0],
            ['key' => 'directHeartRate', 'metricsIndex' => 1],
            ['key' => 'directElevation', 'metricsIndex' => 2],
            ['key' => 'directDoubleCadence', 'metricsIndex' => 3],
            ['key' => 'directLatitude', 'metricsIndex' => 4],
            ['key' => 'directLongitude', 'metricsIndex' => 5],
        ], $details['metricDescriptors']);

        // Tiga entri Samples, satu tanpa inner Sample → 2 baris.
        $this->assertCount(2, $details['activityDetailMetrics']);

        // Titik pertama: timestamp milidetik + HR + Cadence.
        $this->assertSame(
            [self::SAMPLE_MS, 140.0, null, 90.0, null, null],
            $details['activityDetailMetrics'][0]['metrics']
        );

        // Titik kedua: nilai GPS terindeks benar (elevasi index 2).
        $this->assertSame(
            [self::SAMPLE_MS + 1000, 142.0, 55.5, null, -7.56, 110.85],
            $details['activityDetailMetrics'][1]['metrics']
        );
    }

    public function test_derives_heart_rate_from_sml_when_summary_missing(): void
    {
        $result = $this->mapper->toGarminActivity(
            ['startTime' => self::START_MS, 'totalTime' => 300.0],
            ['Data' => ['Samples' => [
                ['TimeISO8601' => '2026-05-09T11:08:01Z', 'Attributes' => ['suunto/sml' => ['Sample' => ['HR' => 100]]]],
                ['TimeISO8601' => '2026-05-09T11:08:02Z', 'Attributes' => ['suunto/sml' => ['Sample' => ['HR' => 140]]]],
            ]]],
        );

        $this->assertSame(120, $result['averageHR']);
        $this->assertSame(140, $result['maxHR']);
    }

    public function test_converts_iso_and_epoch_times_to_app_timezone(): void
    {
        $iso = $this->mapper->toGarminActivity([
            'startTime' => '2026-09-13T00:00:00Z',
            'totalTime' => 60,
        ]);
        $milliseconds = $this->mapper->toGarminActivity([
            'startTime' => self::START_MS,
            'totalTime' => 60,
        ]);
        $seconds = $this->mapper->toGarminActivity([
            'startTime' => 1_789_257_600,
            'totalTime' => 60,
        ]);

        $this->assertSame('2026-09-13 07:00:00', $iso['startTimeLocal']);
        $this->assertSame('2026-09-13 07:00:00', $milliseconds['startTimeLocal']);
        $this->assertSame('2026-09-13 07:00:00', $seconds['startTimeLocal']);
    }

    public function test_converts_duration_milliseconds_to_seconds(): void
    {
        $result = $this->mapper->toGarminActivity([
            'startTime' => self::START_MS,
            'totalTime' => 3_600_000.0,
        ]);

        $this->assertSame(3600, $result['duration']);
    }

    public function test_empty_workout_returns_nulls(): void
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
        $this->assertNull($result['aerobicTrainingEffect']);
        $this->assertNull($result['anaerobicTrainingEffect']);
        $this->assertNull($result['trainingEffectLabel']);
        $this->assertNull($result['activityTrainingLoad']);
        $this->assertNull($result['details']);
        $this->assertNull($result['laps']);
    }

    public function test_average_speed_falls_back_to_distance_over_duration(): void
    {
        $result = $this->mapper->toGarminActivity([
            'startTime' => self::START_MS,
            'totalTime' => 1000.0,
            'totalDistance' => 2500.0,
        ]);

        $this->assertSame(2.5, $result['averageSpeed']);
    }

    #[DataProvider('sportProvider')]
    public function test_normalizes_sport(string $input, string $expected): void
    {
        $this->assertSame($expected, $this->mapper->normalizeSport($input));
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function sportProvider(): array
    {
        return [
            'trail camel' => ['TrailRunning', 'trail_running'],
            'trail enum' => ['TRAIL_RUNNING', 'trail_running'],
            'mtb' => ['MTB', 'mountain_biking'],
            'open water' => ['OpenWaterSwimming', 'open_water_swimming'],
            'road cycling spasi' => ['Road Cycling', 'road_biking'],
            'strip' => ['Indoor-Cycling', 'indoor_cycling'],
            'tak dikenal' => ['Padel-Tennis', 'padel_tennis'],
            'kosong' => ['', 'unknown'],
        ];
    }

    // ---------------------------------------------------------------------
    // sleepSessions
    // ---------------------------------------------------------------------

    public function test_sleep_maps_normal_entry(): void
    {
        $sessions = $this->mapper->sleepSessions([
            [
                'timestamp' => '2026-01-05T23:30:00+01:00',
                'entryData' => [
                    'duration' => 30000,
                    'deepSleepDuration' => 7200,
                    'lightSleepDuration' => 14400,
                    'remSleepDuration' => 7200,
                    'hrAvg' => 1.1,
                    'quality' => 0.87,
                    'avgHrv' => 40,
                    'isNap' => false,
                    'sleepId' => 42,
                ],
            ],
        ], 7);

        $this->assertCount(1, $sessions);
        $session = $sessions[0];
        $this->assertSame(7, $session['user_id']);
        $this->assertSame('2026-01-06 05:30:00', $session['bedtime']);
        $this->assertSame('2026-01-06 13:50:00', $session['wake_time']);
        $this->assertSame(120.0, $session['rem_minutes']);
        $this->assertSame(120.0, $session['deep_minutes']);
        $this->assertSame(240.0, $session['core_minutes']);
        $this->assertSame(20.0, $session['awake_minutes']);
        $this->assertSame(87, $session['sleep_score']);
        $this->assertSame('suunto', $session['source']);
    }

    public function test_sleep_discards_naps_and_dedupes_by_sleep_id(): void
    {
        $sessions = $this->mapper->sleepSessions([
            ['timestamp' => '2026-01-05T23:30:00Z', 'entryData' => ['sleepId' => 7, 'duration' => 1000, 'quality' => 0.5]],
            ['timestamp' => '2026-01-05T23:30:00Z', 'entryData' => ['sleepId' => 7, 'duration' => 5000, 'quality' => 0.9]],
            ['timestamp' => '2026-01-05T23:30:00Z', 'entryData' => ['sleepId' => 7, 'duration' => 3000, 'quality' => 0.7]],
            ['timestamp' => '2026-01-05T14:00:00Z', 'entryData' => ['sleepId' => 8, 'duration' => 1800, 'isNap' => true]],
            ['timestamp' => '2026-01-06T23:30:00Z', 'entryData' => ['sleepId' => 9, 'duration' => 2000, 'quality' => 0]],
        ], 1, 'suunto_cli');

        $this->assertCount(2, $sessions);
        // sleepId 7 → versi duration terpanjang (quality 0.9).
        $this->assertSame(90, $sessions[0]['sleep_score']);
        $this->assertSame(round(5000 / 60, 2), $sessions[0]['awake_minutes']);
        $this->assertSame('suunto_cli', $sessions[0]['source']);
        // quality 0 dianggap tidak diketahui.
        $this->assertNull($sessions[1]['sleep_score']);
    }

    public function test_sleep_skips_entries_without_resolvable_time(): void
    {
        $sessions = $this->mapper->sleepSessions([
            ['entryData' => ['sleepId' => 1, 'duration' => 3600]],
            ['timestamp' => '2026-01-05T23:30:00Z', 'entryData' => ['sleepId' => 2, 'duration' => 0]],
            ['timestamp' => 'bukan-tanggal', 'entryData' => ['sleepId' => 3, 'duration' => 3600]],
            ['timestamp' => '2026-01-05T23:30:00Z', 'entryData' => ['sleepId' => 4, 'duration' => 3600]],
        ], 1);

        $this->assertCount(1, $sessions);
        $this->assertSame('2026-01-06 06:30:00', $sessions[0]['bedtime']);
    }

    // ---------------------------------------------------------------------
    // Fixture
    // ---------------------------------------------------------------------

    /**
     * Isi apa adanya `internal/api/endpoints/testdata/workout_detail_extra_fields.json`.
     *
     * @return array<string, mixed>
     */
    private function detailFixture(): array
    {
        return [
            'key' => 'wk1',
            'username' => 'alice',
            'activityId' => 1,
            'startTime' => 1700000000000,
            'stopTime' => 1700001800000,
            'totalDistance' => 5000.0,
            'totalTime' => 1800.0,
            'energyConsumption' => 321.5,
            'hrdata' => [
                'max' => 172,
                'hrmax' => 190,
                'avg' => 145,
                'userMaxHR' => 190,
                'workoutAvgHR' => 145,
                'workoutMaxHR' => 172,
            ],
            'tss' => [
                'trainingStressScore' => 62.4,
                'calculationMethod' => 'HR',
                'intensityFactor' => 0.84,
                'normalizedPower' => 210,
                'averageGradeAdjustedPace' => 4.75,
            ],
            'tssList' => [
                ['trainingStressScore' => 62.4, 'calculationMethod' => 'HR'],
                ['trainingStressScore' => 58.1, 'calculationMethod' => 'POWER'],
            ],
            'extensions' => [
                ['type' => 'FitnessExtension', 'vo2Max' => 52.1, 'fitnessAge' => 28],
                ['type' => 'SummaryExtension', 'avgPower' => 205, 'peakEpoc' => 88],
            ],
        ];
    }

    /**
     * Potongan `/v1/workouts/{key}/sml` dari tools_read_test.go, entri ketiga
     * metadata-only (tanpa inner Sample).
     *
     * @return array<string, mixed>
     */
    private function smlFixture(): array
    {
        return [
            'Data' => ['Samples' => [
                [
                    'TimeISO8601' => '2026-05-09T11:08:01Z',
                    'Attributes' => ['suunto/sml' => ['Sample' => ['HR' => 140, 'Power' => 220, 'Cadence' => 90]]],
                ],
                [
                    'TimeISO8601' => '2026-05-09T11:08:02Z',
                    'Attributes' => ['suunto/sml' => ['Sample' => [
                        'HR' => 142,
                        'GPSAltitude' => 55.5,
                        'Latitude' => -7.56,
                        'Longitude' => 110.85,
                        'UTC' => self::SAMPLE_MS + 1000,
                    ]]],
                ],
                ['TimeISO8601' => '2026-05-09T11:08:03Z', 'Source' => 'suunto-xxx'],
            ]],
            'Summary' => ['foo' => 'bar'],
        ];
    }
}
