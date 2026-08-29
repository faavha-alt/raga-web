<?php

namespace Tests\Feature;

use App\Models\HrvSample;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * The Garmin feed mixes local-time and GMT fields. With the app running in a
 * non-UTC timezone (Asia/Jakarta in production) the GMT fields must be parsed
 * as UTC, otherwise an activity and its own laps/samples end up 7 hours apart.
 */
class GarminImportTimezoneTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.timezone' => 'Asia/Jakarta']);
        date_default_timezone_set('Asia/Jakarta');
    }

    protected function tearDown(): void
    {
        date_default_timezone_set('UTC');
        parent::tearDown();
    }

    private function import(User $user, array $payload): void
    {
        $path = tempnam(sys_get_temp_dir(), 'garmin_tz_');
        file_put_contents($path, json_encode($payload));

        try {
            Artisan::call('garmin:import', ['path' => $path, '--user' => $user->id]);
        } finally {
            @unlink($path);
        }
    }

    public function test_activity_and_its_laps_share_the_same_start_instant(): void
    {
        $user = User::factory()->create();

        // 08:09:16 WIB == 01:09:16 GMT — the same moment, expressed two ways.
        $this->import($user, [
            'activities' => [[
                'startTimeLocal' => '2026-08-29 08:09:16',
                'duration' => 3600,
                'activityType' => ['typeKey' => 'running'],
                'laps' => ['lapDTOs' => [
                    ['lapIndex' => 1, 'startTimeGMT' => '2026-08-29 01:09:16', 'distance' => 1000, 'duration' => 300],
                ]],
            ]],
        ]);

        $workout = $user->workouts()->firstOrFail();
        $lap = $workout->laps()->firstOrFail();

        $this->assertSame('2026-08-29 08:09:16', $workout->start_date->format('Y-m-d H:i:s'));
        $this->assertTrue(
            $lap->start_time->equalTo($workout->start_date),
            "Lap start {$lap->start_time} should equal workout start {$workout->start_date}",
        );
    }

    public function test_hrv_readings_are_stored_as_utc_instants(): void
    {
        $user = User::factory()->create();

        $this->import($user, [
            'daily' => [[
                'date' => '2026-08-29',
                'hrv' => ['hrvReadings' => [
                    ['readingTimeGMT' => '2026-08-28T19:00:00.0', 'hrvValue' => 65],
                ]],
            ]],
        ]);

        $sample = HrvSample::where('user_id', $user->id)->firstOrFail();

        // 19:00 GMT == 02:00 WIB next day.
        $this->assertSame('2026-08-29 02:00:00', $sample->timestamp->format('Y-m-d H:i:s'));
        $this->assertSame('2026-08-28 19:00:00', $sample->timestamp->utc()->format('Y-m-d H:i:s'));
    }
}
