<?php

namespace Tests\Feature;

use App\Models\Segment;
use App\Models\SegmentEffort;
use App\Models\User;
use App\Models\Workout;
use App\Models\WorkoutSample;
use App\Services\Segment\PolylineCodec;
use App\Services\Segment\SegmentScanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use RuntimeException;
use Tests\TestCase;

class SegmentTest extends TestCase
{
    use RefreshDatabase;

    private const TRACK_LAT = -7.5;

    private const TRACK_LNG_START = 110.0;

    public function test_guest_is_redirected_from_segments_index(): void
    {
        $this->get('/segments')->assertRedirect(route('login'));
    }

    public function test_creating_a_segment_persists_the_computed_distance_and_matches_the_workout(): void
    {
        $user = User::factory()->create();
        $workout = $this->makeWorkout($user, 'running', Carbon::parse('2026-09-01 07:00:00'));
        $this->addStraightTrack($workout);

        $response = $this->actingAs($user)->post('/segments', $this->payload($workout, 1, 3, 'Tanjakan Uji'));

        $response->assertRedirect();

        $segment = Segment::query()->firstOrFail();
        $this->assertSame('Tanjakan Uji', $segment->name);
        $this->assertSame('running', $segment->activity_type);
        $this->assertTrue($segment->is_public);
        $this->assertSame($user->id, $segment->user_id);

        $adjacent = $this->haversineMeters(
            self::TRACK_LAT,
            self::TRACK_LNG_START + 0.001,
            self::TRACK_LAT,
            self::TRACK_LNG_START + 0.002,
        );
        $expected = 2 * $adjacent;

        $this->assertGreaterThan(100.0, $segment->distance_meters);
        $this->assertEqualsWithDelta($expected, $segment->distance_meters, $expected * 0.05);

        $this->assertNotNull($segment->encoded_polyline);
        $this->assertSame(3, count(PolylineCodec::decode($segment->encoded_polyline)));

        $this->assertDatabaseCount('segment_efforts', 1);
        $effort = SegmentEffort::query()->firstOrFail();
        $this->assertSame($workout->id, $effort->workout_id);
        $this->assertSame(20.0, $effort->elapsed_seconds);
        $this->assertSame(1, $segment->refresh()->effort_count);
    }

    public function test_creating_a_too_short_segment_is_rejected(): void
    {
        $user = User::factory()->create();
        $workout = $this->makeWorkout($user, 'running', Carbon::parse('2026-09-01 07:00:00'));
        $this->addStraightTrack($workout, step: 0.0001, count: 5);

        $response = $this->actingAs($user)->post('/segments', $this->payload($workout, 1, 2, 'Terlalu Pendek'));

        $response->assertSessionHasErrors('end_index');
        $this->assertDatabaseCount('segments', 0);
    }

    public function test_a_user_cannot_create_a_segment_from_another_users_workout(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $workout = $this->makeWorkout($otherUser, 'running', Carbon::parse('2026-09-01 07:00:00'));
        $this->addStraightTrack($workout);

        $response = $this->actingAs($user)->post('/segments', $this->payload($workout, 1, 3, 'Curang'));

        $response->assertNotFound();
        $this->assertDatabaseCount('segments', 0);
    }

    public function test_create_page_renders_for_own_workout_and_404s_for_another_users_workout(): void
    {
        $user = User::factory()->create();
        $workout = $this->makeWorkout($user, 'running', Carbon::parse('2026-09-01 07:00:00'));
        $this->addStraightTrack($workout);

        $own = $this->actingAs($user)->get('/segments/create?workout_id='.$workout->id);
        $own->assertOk();
        $own->assertSee('Tentukan Titik Awal', false);

        $otherUser = User::factory()->create();
        $otherWorkout = $this->makeWorkout($otherUser, 'running', Carbon::parse('2026-09-01 07:00:00'));
        $this->addStraightTrack($otherWorkout);

        $this->actingAs($user)
            ->get('/segments/create?workout_id='.$otherWorkout->id)
            ->assertNotFound();
    }

    public function test_rescan_keeps_only_the_fastest_effort_for_a_workout(): void
    {
        $user = User::factory()->create();
        $workout = $this->makeWorkout($user, 'running', Carbon::parse('2026-09-01 07:00:00'));
        $this->addStraightTrack($workout);

        $this->actingAs($user)->post('/segments', $this->payload($workout, 1, 3, 'Segmen Cepat'))->assertRedirect();

        $segment = Segment::query()->firstOrFail();
        $this->assertSame(20.0, SegmentEffort::query()->firstOrFail()->elapsed_seconds);

        // Aktivitas yang sama dilewati lebih cepat: 5 detik antar sampel.
        $workout->samples()->delete();
        $this->addStraightTrack($workout, intervalSeconds: 5);

        $this->actingAs($user)->post('/segments/'.$segment->id.'/rescan')->assertRedirect();

        $this->assertDatabaseCount('segment_efforts', 1);
        $this->assertSame(10.0, SegmentEffort::query()->firstOrFail()->elapsed_seconds);
        $this->assertSame(1, $segment->refresh()->effort_count);
    }

    public function test_rescan_finds_efforts_of_other_athletes(): void
    {
        $owner = User::factory()->create();
        $rider = User::factory()->create();
        $ownerWorkout = $this->makeWorkout($owner, 'running', Carbon::parse('2026-09-01 07:00:00'));
        $this->addStraightTrack($ownerWorkout);

        $this->actingAs($owner)->post('/segments', $this->payload($ownerWorkout, 1, 3, 'Segmen Bersama'))->assertRedirect();
        $segment = Segment::query()->firstOrFail();

        $riderWorkout = $this->makeWorkout($rider, 'trail_running', Carbon::parse('2026-09-02 07:00:00'));
        $this->addStraightTrack($riderWorkout);

        $this->actingAs($owner)->post('/segments/'.$segment->id.'/rescan')->assertRedirect();

        $this->assertDatabaseCount('segment_efforts', 2);
        $this->assertDatabaseHas('segment_efforts', ['segment_id' => $segment->id, 'user_id' => $rider->id]);
        $this->assertSame(2, $segment->refresh()->effort_count);
    }

    public function test_leaderboard_orders_athletes_by_fastest_time_with_one_row_per_athlete(): void
    {
        $owner = User::factory()->create(['name' => 'Atlet Alpha']);
        $fast = User::factory()->create(['name' => 'Atlet Bravo']);
        $slow = User::factory()->create(['name' => 'Atlet Charlie']);

        $ownerWorkout = $this->makeWorkout($owner, 'running', Carbon::parse('2026-09-01 07:00:00'));
        $this->addStraightTrack($ownerWorkout);
        $this->actingAs($owner)->post('/segments', $this->payload($ownerWorkout, 1, 3, 'Segmen Lomba'))->assertRedirect();
        $segment = Segment::query()->firstOrFail();

        // Bravo punya dua usaha (tercepat menang), Charlie satu.
        $fastWorkoutA = $this->makeWorkout($fast, 'running', Carbon::parse('2026-09-02 07:00:00'));
        $this->addStraightTrack($fastWorkoutA, intervalSeconds: 3);
        $fastWorkoutB = $this->makeWorkout($fast, 'running', Carbon::parse('2026-09-03 07:00:00'));
        $this->addStraightTrack($fastWorkoutB, intervalSeconds: 8);

        $slowWorkout = $this->makeWorkout($slow, 'running', Carbon::parse('2026-09-04 07:00:00'));
        $this->addStraightTrack($slowWorkout, intervalSeconds: 5);

        $this->actingAs($owner)->post('/segments/'.$segment->id.'/rescan')->assertRedirect();

        $response = $this->actingAs($owner)->get('/segments/'.$segment->id);
        $response->assertOk();

        // 6 s (Bravo) < 10 s (Charlie) < 20 s (Alpha).
        $response->assertSeeInOrder(['Atlet Bravo', 'Atlet Charlie', 'Atlet Alpha']);
        $response->assertSee('PB');

        $html = $response->getContent();
        $this->assertSame(1, substr_count($html, 'Atlet Bravo'));
        $this->assertSame(1, substr_count($html, 'Atlet Charlie'));
    }

    public function test_leaderboard_hides_efforts_from_another_users_private_workout(): void
    {
        $owner = User::factory()->create(['name' => 'Atlet Alpha']);
        $privateAthlete = User::factory()->create(['name' => 'Atlet Privat']);

        $ownerWorkout = $this->makeWorkout($owner, 'running', Carbon::parse('2026-09-01 07:00:00'));
        $this->addStraightTrack($ownerWorkout);
        $this->actingAs($owner)->post('/segments', $this->payload($ownerWorkout, 1, 3, 'Segmen Privasi'))->assertRedirect();
        $segment = Segment::query()->firstOrFail();

        $privateWorkout = $this->makeWorkout($privateAthlete, 'running', Carbon::parse('2026-09-02 07:00:00'), 'private');
        $this->addStraightTrack($privateWorkout, intervalSeconds: 4);

        $this->actingAs($owner)->post('/segments/'.$segment->id.'/rescan')->assertRedirect();

        // Effort tetap tersimpan (data milik pemilik aktivitas)...
        $this->assertDatabaseHas('segment_efforts', [
            'segment_id' => $segment->id,
            'workout_id' => $privateWorkout->id,
        ]);

        // ...tetapi tidak pernah tampil di leaderboard orang lain.
        $viewer = User::factory()->create();
        $this->actingAs($viewer)
            ->get('/segments/'.$segment->id)
            ->assertOk()
            ->assertDontSee('Atlet Privat');

        // Pemilik aktivitas tetap melihat usahanya sendiri.
        $this->actingAs($privateAthlete)
            ->get('/segments/'.$segment->id)
            ->assertOk()
            ->assertSee('Atlet Privat');
    }

    public function test_only_the_owner_can_delete_a_segment(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $segment = $this->makeSegment($owner, 'Segmen Hapus');

        // Non-owner mendapat 404 (bukan 403) agar keberadaan segment tidak
        // terkonfirmasi lewat perbedaan status.
        $this->actingAs($otherUser)
            ->delete('/segments/'.$segment->id)
            ->assertNotFound();

        $this->assertModelExists($segment);

        $this->actingAs($owner)
            ->delete('/segments/'.$segment->id)
            ->assertRedirect(route('segments.index'));

        $this->assertDatabaseMissing('segments', ['id' => $segment->id]);
    }

    public function test_only_the_owner_can_rescan_a_segment(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $segment = $this->makeSegment($owner, 'Segmen Pindai');

        $this->actingAs($otherUser)
            ->post('/segments/'.$segment->id.'/rescan')
            ->assertNotFound();

        $this->actingAs($owner)
            ->post('/segments/'.$segment->id.'/rescan')
            ->assertRedirect(route('segments.show', $segment));
    }

    public function test_private_segment_returns_not_found_for_non_owner(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $segment = $this->makeSegment($owner, 'Segmen Rahasia', isPublic: false);

        $this->actingAs($otherUser)
            ->get('/segments/'.$segment->id)
            ->assertNotFound();

        $this->actingAs($owner)
            ->get('/segments/'.$segment->id)
            ->assertOk()
            ->assertSee('Segmen Rahasia');
    }

    public function test_segments_index_lists_public_and_own_private_but_not_others_private(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $this->makeSegment($user, 'Segmen Publik Saya', isPublic: true);
        $this->makeSegment($user, 'Segmen Privat Saya', isPublic: false);
        $this->makeSegment($otherUser, 'Segmen Privat Orang', isPublic: false);

        $response = $this->actingAs($user)->get('/segments');

        $response->assertOk();
        $response->assertSee('Segmen Publik Saya');
        $response->assertSee('Segmen Privat Saya');
        $response->assertDontSee('Segmen Privat Orang');
    }

    public function test_segments_index_filters_by_name(): void
    {
        $user = User::factory()->create();
        $this->makeSegment($user, 'Tanjakan Bukit Cinta');
        $this->makeSegment($user, 'Sprint Lurus Kota');

        $response = $this->actingAs($user)->get('/segments?search=Bukit');

        $response->assertOk();
        $response->assertSee('Tanjakan Bukit Cinta');
        $response->assertDontSee('Sprint Lurus Kota');
    }

    public function test_segments_show_lists_the_viewers_own_attempts_separately(): void
    {
        $user = User::factory()->create();
        $workout = $this->makeWorkout($user, 'running', Carbon::parse('2026-09-01 07:00:00'));
        $this->addStraightTrack($workout);

        $this->actingAs($user)->post('/segments', $this->payload($workout, 1, 3, 'Segmen Saya'))->assertRedirect();
        $segment = Segment::query()->firstOrFail();

        $response = $this->actingAs($user)->get('/segments/'.$segment->id);

        $response->assertOk();
        $response->assertSee('Effort Saya');
        $response->assertSee('0:20');
    }

    public function test_public_effort_count_excludes_efforts_from_private_workouts(): void
    {
        $owner = User::factory()->create(['name' => 'Atlet Alpha']);
        $privateAthlete = User::factory()->create(['name' => 'Atlet Privat']);

        $ownerWorkout = $this->makeWorkout($owner, 'running', Carbon::parse('2026-09-01 07:00:00'));
        $this->addStraightTrack($ownerWorkout);
        $this->actingAs($owner)->post('/segments', $this->payload($ownerWorkout, 1, 3, 'Segmen Hitung'))->assertRedirect();
        $segment = Segment::query()->firstOrFail();

        // Lima aktivitas private milik atlet lain juga melewati segment ini.
        for ($i = 0; $i < 5; $i++) {
            $privateWorkout = $this->makeWorkout(
                $privateAthlete,
                'running',
                Carbon::parse('2026-09-02 07:00:00')->addDays($i),
                'private',
            );
            $this->addStraightTrack($privateWorkout, intervalSeconds: 5 + $i);
        }

        $this->actingAs($owner)->post('/segments/'.$segment->id.'/rescan')->assertRedirect();

        // Kolom denormalisasi memang menghitung SEMUA effort (6), tetapi angka
        // itu tidak boleh dirender: viewer pemilik segment hanya melihat 1.
        $this->assertSame(6, $segment->refresh()->effort_count);

        $showHtml = $this->actingAs($owner)
            ->get('/segments/'.$segment->id)
            ->assertOk()
            ->getContent();

        $this->assertSame(1, $this->renderedEffortCount($showHtml, 'Total Effort'));

        $indexHtml = $this->actingAs($owner)
            ->get('/segments')
            ->assertOk()
            ->getContent();

        $this->assertSame(1, $this->renderedEffortCount($indexHtml, 'Effort'));
    }

    public function test_private_workout_effort_is_hidden_from_others_but_visible_to_its_owner(): void
    {
        $owner = User::factory()->create(['name' => 'Atlet Alpha']);
        $privateAthlete = User::factory()->create(['name' => 'Atlet Privat']);

        $ownerWorkout = $this->makeWorkout($owner, 'running', Carbon::parse('2026-09-01 07:00:00'));
        $this->addStraightTrack($ownerWorkout);
        $this->actingAs($owner)->post('/segments', $this->payload($ownerWorkout, 1, 3, 'Segmen Privasi Dua'))->assertRedirect();
        $segment = Segment::query()->firstOrFail();

        $privateWorkout = $this->makeWorkout($privateAthlete, 'running', Carbon::parse('2026-09-02 07:00:00'), 'private');
        $this->addStraightTrack($privateWorkout, intervalSeconds: 4);
        $this->actingAs($owner)->post('/segments/'.$segment->id.'/rescan')->assertRedirect();

        $viewer = User::factory()->create();
        $this->actingAs($viewer)
            ->get('/segments/'.$segment->id)
            ->assertOk()
            ->assertDontSee('Atlet Privat')
            ->assertDontSee('2026-09-02');

        $this->actingAs($privateAthlete)
            ->get('/segments/'.$segment->id)
            ->assertOk()
            ->assertSee('Atlet Privat')
            ->assertSee('0:08');
    }

    public function test_leaderboard_never_renders_another_athletes_heart_rate(): void
    {
        $owner = User::factory()->create(['name' => 'Atlet Pemilik']);
        $viewer = User::factory()->create(['name' => 'Atlet Penonton']);

        $ownerWorkout = $this->makeWorkout($owner, 'running', Carbon::parse('2026-09-01 07:00:00'));
        $this->addStraightTrack($ownerWorkout, heartRate: 155);
        $this->actingAs($owner)->post('/segments', $this->payload($ownerWorkout, 1, 3, 'Segmen Jantung'))->assertRedirect();
        $segment = Segment::query()->firstOrFail();

        $viewerWorkout = $this->makeWorkout($viewer, 'running', Carbon::parse('2026-09-02 07:00:00'));
        $this->addStraightTrack($viewerWorkout, heartRate: 132, intervalSeconds: 12);
        $this->actingAs($owner)->post('/segments/'.$segment->id.'/rescan')->assertRedirect();

        $response = $this->actingAs($viewer)->get('/segments/'.$segment->id);

        $response->assertOk();
        // HR atlet lain (pemilik segment) tidak dirender sama sekali...
        $response->assertDontSee('155 bpm');
        // ...tetapi HR milik viewer sendiri tetap tampil.
        $response->assertSee('132 bpm');
        // Atlet lain tetap tampil lengkap tanpa kolom HR-nya.
        $response->assertSee('Atlet Pemilik');
        $response->assertSee('0:20');
    }

    public function test_segment_creation_is_atomic_when_the_scan_fails(): void
    {
        $user = User::factory()->create();
        $workout = $this->makeWorkout($user, 'running', Carbon::parse('2026-09-01 07:00:00'));
        $this->addStraightTrack($workout);

        $this->mock(SegmentScanService::class)
            ->shouldReceive('scan')
            ->once()
            ->andThrow(new RuntimeException('pemindaian gagal'));

        $this->withoutExceptionHandling();

        try {
            $this->actingAs($user)->post('/segments', $this->payload($workout, 1, 3, 'Segmen Atomik'));
            $this->fail('Pemindaian yang gagal seharusnya melempar exception.');
        } catch (RuntimeException $exception) {
            $this->assertSame('pemindaian gagal', $exception->getMessage());
        }

        $this->assertDatabaseMissing('segments', ['name' => 'Segmen Atomik']);
        $this->assertDatabaseCount('segments', 0);
        $this->assertDatabaseCount('segment_efforts', 0);
    }

    public function test_segment_creation_defaults_to_private_when_is_public_is_omitted(): void
    {
        $user = User::factory()->create();
        $workout = $this->makeWorkout($user, 'running', Carbon::parse('2026-09-01 07:00:00'));
        $this->addStraightTrack($workout);

        $response = $this->actingAs($user)->post('/segments', [
            'workout_id' => $workout->id,
            'start_index' => 1,
            'end_index' => 3,
            'name' => 'Segmen Default Privat',
        ]);

        $response->assertRedirect();

        $segment = Segment::query()->firstOrFail();
        $this->assertFalse($segment->is_public);
        $this->assertDatabaseHas('segments', ['id' => $segment->id, 'is_public' => false]);
    }

    public function test_repeated_rescan_does_not_duplicate_efforts(): void
    {
        $user = User::factory()->create();
        $workout = $this->makeWorkout($user, 'running', Carbon::parse('2026-09-01 07:00:00'));
        $this->addStraightTrack($workout);

        $this->actingAs($user)->post('/segments', $this->payload($workout, 1, 3, 'Segmen Ganda'))->assertRedirect();
        $segment = Segment::query()->firstOrFail();

        $this->actingAs($user)->post('/segments/'.$segment->id.'/rescan')->assertRedirect();
        $this->actingAs($user)->post('/segments/'.$segment->id.'/rescan')->assertRedirect();

        $this->assertDatabaseCount('segment_efforts', 1);
        $this->assertSame(1, $segment->refresh()->effort_count);
    }

    public function test_segments_index_can_sort_by_visible_effort_count(): void
    {
        $user = User::factory()->create();
        $this->makeSegment($user, 'Segmen Sedikit');
        $this->makeSegment($user, 'Segmen Banyak');

        $response = $this->actingAs($user)->get('/segments?sort=efforts&direction=asc');

        $response->assertOk();
        $response->assertSee('Segmen Sedikit');
        $response->assertSee('Segmen Banyak');
    }

    public function test_segments_index_search_escapes_like_wildcards(): void
    {
        $user = User::factory()->create();
        $this->makeSegment($user, 'Tanjakan 50% Bukit');
        $this->makeSegment($user, 'Jalur_Kota');
        $this->makeSegment($user, 'Sprint Lurus Kota');

        // `%` tanpa escape akan cocok dengan SEMUA nama.
        $percent = $this->actingAs($user)->get('/segments?search=%25');
        $percent->assertOk();
        $percent->assertSee('Tanjakan 50% Bukit');
        $percent->assertDontSee('Sprint Lurus Kota');

        // `_` tanpa escape akan cocok dengan karakter apa pun.
        $underscore = $this->actingAs($user)->get('/segments?search=_');
        $underscore->assertOk();
        $underscore->assertSee('Jalur_Kota');
        $underscore->assertDontSee('Sprint Lurus Kota');
    }

    /**
     * Ambil angka pada tile berlabel $label — dipakai sebagai guard agar angka
     * mentah `segments.effort_count` tidak pernah dirender lagi.
     */
    private function renderedEffortCount(string $html, string $label): ?int
    {
        $pattern = '/>'.preg_quote($label, '/').'<\/p>.*?<p[^>]*>\s*(\d+)/s';

        if (preg_match($pattern, $html, $matches) !== 1) {
            return null;
        }

        return (int) $matches[1];
    }

    private function makeWorkout(User $user, string $type, Carbon $start, string $visibility = 'public'): Workout
    {
        return Workout::create([
            'user_id' => $user->id,
            'type' => $type,
            'name' => 'Aktivitas '.$start->format('d M Y'),
            'start_date' => $start,
            'end_date' => $start->copy()->addHour(),
            'distance_meters' => 6000,
            'average_pace_seconds_per_km' => 320,
            'average_heart_rate' => 140,
            'visibility' => $visibility,
            'source' => 'garmin',
        ]);
    }

    /**
     * Lintasan lurus ke timur; sampel ke-i berjarak `step` derajat dari
     * sampel sebelumnya dengan selisih waktu `intervalSeconds` detik.
     */
    private function addStraightTrack(
        Workout $workout,
        float $step = 0.001,
        int $count = 10,
        int $intervalSeconds = 10,
        int $heartRate = 140,
    ): void {
        $timestamp = $workout->start_date->copy();

        for ($i = 0; $i < $count; $i++) {
            WorkoutSample::create([
                'workout_id' => $workout->id,
                'timestamp' => $timestamp->copy(),
                'latitude' => self::TRACK_LAT,
                'longitude' => self::TRACK_LNG_START + ($i * $step),
                'altitude_meters' => 100 + ($i * 2),
                'heart_rate' => $heartRate,
                'pace_seconds_per_km' => 300,
            ]);

            $timestamp->addSeconds($intervalSeconds);
        }
    }

    private function makeSegment(User $user, string $name, bool $isPublic = true): Segment
    {
        return $user->ownedSegments()->create([
            'name' => $name,
            'activity_type' => 'running',
            'distance_meters' => 500,
            'elevation_gain_meters' => 10,
            'average_grade_percent' => 2,
            'start_lat' => self::TRACK_LAT,
            'start_lng' => self::TRACK_LNG_START,
            'end_lat' => self::TRACK_LAT,
            'end_lng' => self::TRACK_LNG_START + 0.005,
            'encoded_polyline' => null,
            'is_public' => $isPublic,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Workout $workout, int $startIndex, int $endIndex, string $name): array
    {
        return [
            'workout_id' => $workout->id,
            'start_index' => $startIndex,
            'end_index' => $endIndex,
            'name' => $name,
            'is_public' => '1',
        ];
    }

    private function haversineMeters(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371000;
        $deltaLat = deg2rad($lat2 - $lat1);
        $deltaLng = deg2rad($lng2 - $lng1);

        $h = sin($deltaLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($deltaLng / 2) ** 2;

        return $earthRadius * 2 * atan2(sqrt($h), sqrt(1 - $h));
    }
}
