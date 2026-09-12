<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Workout;
use App\Models\WorkoutSample;
use App\Services\Recording\RecordingService;
use App\Support\ActivityVisibility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Perekaman GPS dari browser: validasi, penghitungan, dan persistensi.
 *
 * Trek sintetis dipakai supaya jarak bisa diprediksi: 0,001 derajat lintang
 * pada bujur 0 kira-kira 111,195 meter (haversine).
 */
class RecordingTest extends TestCase
{
    use RefreshDatabase;

    /** Perkiraan panjang 1 derajat lintang di ekuator (meter); 0,001° ≈ 111,195 m. */
    private const METERS_PER_DEGREE_LAT = 111194.93;

    public function test_guest_is_redirected_to_login_from_record_page(): void
    {
        $response = $this->get(route('record.index'));

        $response->assertRedirect(route('login'));
    }

    public function test_guest_is_redirected_to_login_from_store(): void
    {
        $response = $this->post(route('recordings.store'), []);

        $response->assertRedirect(route('login'));
        $this->assertDatabaseCount('workouts', 0);
    }

    public function test_authenticated_user_can_open_record_page(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('record.index'));

        $response->assertOk();
        $response->assertSee('Rekam Aktivitas');
    }

    public function test_record_page_preselects_private_visibility(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('record.index'));

        $response->assertOk();

        $html = $response->getContent();

        // Radio "Hanya saya" harus ter-render checked, dan "Semua orang" tidak.
        $this->assertMatchesRegularExpression('/<input[^>]*value="private"[^>]*checked/', $html);
        $this->assertDoesNotMatchRegularExpression('/<input[^>]*value="public"[^>]*checked/', $html);

        // State Alpine awal & fallback pemulihan localStorage tidak boleh
        // kembali ke 'public' (regression guard K1).
        $this->assertStringContainsString('defaultVisibility', $html);
        $this->assertStringNotContainsString("visibility: 'public'", $html);
        $this->assertStringNotContainsString("|| 'public'", $html);
    }

    public function test_out_of_range_point_timestamp_is_rejected_and_creates_no_workout(): void
    {
        $user = User::factory()->create();

        $points = $this->straightTrack();
        $points[5]['t'] = 999999999999999999;

        $response = $this->actingAs($user)->post(route('recordings.store'), $this->payload([
            'points' => $points,
        ]));

        $response->assertSessionHasErrors('points.5.t');
        $this->assertDatabaseCount('workouts', 0);
        $this->assertDatabaseCount('workout_samples', 0);
    }

    public function test_track_duration_beyond_maximum_is_rejected(): void
    {
        $user = User::factory()->create();

        $start = $this->startMs();
        $points = [
            $this->point($start, 0.0),
            $this->point($start + RecordingService::MAX_TRACK_DURATION_MS + 1000, 0.001),
        ];

        $response = $this->actingAs($user)->post(route('recordings.store'), $this->payload([
            'points' => $points,
        ]));

        $response->assertSessionHasErrors('points');
        $this->assertDatabaseCount('workouts', 0);
    }

    public function test_valid_payload_creates_workout_with_expected_distance_and_samples(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('recordings.store'), $this->payload());

        $response->assertRedirect();

        $workout = Workout::firstOrFail();

        $this->assertModelExists($workout);
        $this->assertSame($user->id, $workout->user_id);
        $this->assertSame('running', $workout->type);
        $this->assertSame('browser', $workout->source);
        $this->assertSame(11, $workout->samples()->count());

        $expected = 10 * 0.001 * self::METERS_PER_DEGREE_LAT;
        $this->assertTrue(
            abs($workout->distance_meters - $expected) / $expected < 0.05,
            "Jarak {$workout->distance_meters} m di luar 5% dari {$expected} m.",
        );
    }

    public function test_visibility_defaults_to_private_when_omitted(): void
    {
        $user = User::factory()->create();

        $payload = $this->payload();
        unset($payload['visibility']);

        $this->actingAs($user)->post(route('recordings.store'), $payload)->assertRedirect();

        $workout = Workout::firstOrFail();

        $this->assertSame(ActivityVisibility::Private, $workout->fresh()->visibility);
        $this->assertDatabaseHas('workouts', ['id' => $workout->id, 'visibility' => 'private']);
    }

    public function test_explicit_public_visibility_is_honoured(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(
            route('recordings.store'),
            $this->payload(['visibility' => ActivityVisibility::Public->value]),
        )->assertRedirect();

        $workout = Workout::firstOrFail();

        $this->assertSame(ActivityVisibility::Public, $workout->fresh()->visibility);
        $this->assertDatabaseHas('workouts', ['id' => $workout->id, 'visibility' => 'public']);
    }

    public function test_fewer_than_two_points_is_rejected_and_creates_no_workout(): void
    {
        $user = User::factory()->create();

        $points = [$this->point($this->startMs(), 0.0)];

        $response = $this->actingAs($user)->post(route('recordings.store'), $this->payload([
            'points' => $points,
        ]));

        $response->assertSessionHasErrors('points');
        $this->assertDatabaseCount('workouts', 0);
        $this->assertDatabaseCount('workout_samples', 0);
    }

    public function test_out_of_range_latitude_is_rejected(): void
    {
        $user = User::factory()->create();

        $points = $this->straightTrack();
        $points[0]['lat'] = 91.5;

        $response = $this->actingAs($user)->post(route('recordings.store'), $this->payload([
            'points' => $points,
        ]));

        $response->assertSessionHasErrors('points.0.lat');
        $this->assertDatabaseCount('workouts', 0);
    }

    public function test_non_monotonic_timestamps_are_rejected(): void
    {
        $user = User::factory()->create();

        $points = $this->straightTrack();
        $points[3]['t'] = $points[2]['t'] - 1000;

        $response = $this->actingAs($user)->post(route('recordings.store'), $this->payload([
            'points' => $points,
        ]));

        $response->assertSessionHasErrors('points');
        $this->assertDatabaseCount('workouts', 0);
    }

    public function test_gps_teleport_does_not_inflate_distance(): void
    {
        $user = User::factory()->create();

        $points = $this->straightTrack();

        // Titik glitch 5,5 km dari jalur, hanya 15 detik setelah titik sebelumnya.
        $glitch = [
            't' => $points[4]['t'] + 15000,
            'lat' => 0.05,
            'lng' => 0.0,
            'alt' => 100.0,
        ];
        array_splice($points, 5, 0, [$glitch]);

        $this->actingAs($user)->post(route('recordings.store'), $this->payload([
            'points' => $points,
        ]))->assertRedirect();

        $workout = Workout::firstOrFail();

        // Trek asli tetap ~1,11 km; lompatan 5,5 km harus dibuang.
        $this->assertTrue($workout->distance_meters > 900, 'Jarak terlalu kecil: '.$workout->distance_meters);
        $this->assertTrue($workout->distance_meters < 1500, 'Jarak meledak karena glitch: '.$workout->distance_meters);
    }

    public function test_elevation_gain_ignores_small_altitude_noise(): void
    {
        $user = User::factory()->create();

        $points = $this->noisyTrack(60);

        $this->actingAs($user)->post(route('recordings.store'), $this->payload([
            'points' => $points,
        ]))->assertRedirect();

        $workout = Workout::firstOrFail();

        $this->assertTrue(
            $workout->elevation_gain_meters < 3,
            'Noise altitude dianggap tanjakan: '.$workout->elevation_gain_meters,
        );
    }

    public function test_elevation_gain_captures_real_climb(): void
    {
        $user = User::factory()->create();

        $points = $this->noisyTrack(60);
        $start = $this->startMs();

        // Tanjakan nyata: +5 m per titik selama 6 titik terakhir.
        for ($i = 0; $i < 6; $i++) {
            $points[] = [
                't' => $start + (60 + $i) * 10000,
                'lat' => (60 + $i) * 0.0005,
                'lng' => 0.0,
                'alt' => 100.0 + ($i + 1) * 5,
            ];
        }

        $this->actingAs($user)->post(route('recordings.store'), $this->payload([
            'points' => $points,
        ]))->assertRedirect();

        $workout = Workout::firstOrFail();

        $this->assertTrue(
            $workout->elevation_gain_meters > 10,
            'Tanjakan nyata tidak terdeteksi: '.$workout->elevation_gain_meters,
        );
        $this->assertTrue(
            $workout->elevation_gain_meters < 50,
            'Elevation gain terlalu besar: '.$workout->elevation_gain_meters,
        );
    }

    public function test_recording_does_not_create_rows_for_another_user(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $this->actingAs($owner)->post(route('recordings.store'), $this->payload())->assertRedirect();

        $workout = Workout::firstOrFail();

        $this->assertSame($owner->id, $workout->user_id);
        $this->assertSame(0, Workout::where('user_id', $other->id)->count());
        $this->assertSame(0, WorkoutSample::whereIn('workout_id', $other->workouts()->select('id'))->count());
        $this->assertSame(11, WorkoutSample::where('workout_id', $workout->id)->count());
    }

    public function test_manual_distance_and_duration_work_without_gps_points(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('recordings.store'), [
            'type' => 'running',
            'name' => 'Treadmill',
            'started_at' => Carbon::createFromTimestampMs($this->startMs())->toIso8601String(),
            'manual_distance_meters' => 5000,
            'manual_duration_seconds' => 1800,
            'points' => [],
        ])->assertRedirect();

        $workout = Workout::firstOrFail();

        $this->assertSame('browser', $workout->source);
        $this->assertSame(5000.0, $workout->distance_meters);
        $this->assertSame(1800, $workout->durationSeconds());
        $this->assertSame(360.0, $workout->average_pace_seconds_per_km);
        $this->assertSame(0, $workout->samples()->count());
    }

    public function test_unknown_visibility_is_rejected(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('recordings.store'), $this->payload([
            'visibility' => 'publik-banget',
        ]));

        $response->assertSessionHasErrors('visibility');
        $this->assertDatabaseCount('workouts', 0);
    }

    public function test_missing_sport_type_is_rejected(): void
    {
        $user = User::factory()->create();

        $payload = $this->payload();
        unset($payload['type']);

        $response = $this->actingAs($user)->post(route('recordings.store'), $payload);

        $response->assertSessionHasErrors('type');
        $this->assertDatabaseCount('workouts', 0);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'type' => 'running',
            'name' => 'Lari pagi',
            'visibility' => ActivityVisibility::Private->value,
            'started_at' => Carbon::createFromTimestampMs($this->startMs())->toIso8601String(),
            'elapsed_seconds' => 300,
            'points' => $this->straightTrack(),
        ], $overrides);
    }

    private function startMs(): int
    {
        return Carbon::parse('2026-09-01 06:00:00', 'Asia/Jakarta')->getTimestampMs();
    }

    /**
     * Trek lurus 10 segmen, tiap segmen 0,001 derajat lintang (~111 m),
     * selang 30 detik — laju ~3,7 m/s, jauh di bawah ambang glitch.
     *
     * @return list<array<string, mixed>>
     */
    private function straightTrack(): array
    {
        $start = $this->startMs();
        $points = [];

        for ($i = 0; $i <= 10; $i++) {
            $points[] = $this->point($start + $i * 30000, $i * 0.001);
        }

        return $points;
    }

    /**
     * Altitude yang bergantian +1 m/-1 m — noise murni, bukan tanjakan.
     *
     * @return list<array<string, mixed>>
     */
    private function noisyTrack(int $count): array
    {
        $start = $this->startMs();
        $points = [];

        for ($i = 0; $i < $count; $i++) {
            $points[] = [
                't' => $start + $i * 10000,
                'lat' => $i * 0.0005,
                'lng' => 0.0,
                'alt' => $i % 2 === 0 ? 100.0 : 101.0,
            ];
        }

        return $points;
    }

    /**
     * @return array<string, mixed>
     */
    private function point(int $t, float $lat): array
    {
        return ['t' => $t, 'lat' => $lat, 'lng' => 0.0, 'alt' => 100.0];
    }
}
