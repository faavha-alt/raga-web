<?php

namespace App\Services\Recording;

use App\Models\User;
use App\Models\Workout;
use App\Models\WorkoutLap;
use App\Models\WorkoutSample;
use App\Support\ActivityVisibility;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Membangun Workout + WorkoutSample + WorkoutLap dari payload perekaman GPS
 * browser. Semua penghitungan ada di TrackMetricsCalculator; kelas ini hanya
 * menormalkan payload, memanggil kalkulator, lalu menyimpan dalam satu
 * transaksi supaya kegagalan tidak meninggalkan aktivitas setengah jadi.
 *
 * `source` = 'browser' menandai aktivitas ini bukan hasil impor Garmin.
 */
class RecordingService
{
    public const SOURCE = 'browser';

    /** Nilai typeKey yang sama dengan Garmin (dipakai ActivityTypeIcon). */
    public const SPORT_TYPES = ['running', 'trail_running', 'cycling', 'walking', 'hiking'];

    /** Batas aman jumlah titik yang disimpan dalam satu aktivitas. */
    public const MAX_POINTS = 100000;

    /**
     * Batas durasi trek: 72 jam. Jauh lebih longgar dari ultra-marathon
     * (48 jam), tetapi mencegah timestamp liar (mis. 999999999999999999 ms)
     * menghasilkan tanggal Carbon di luar jangkauan MySQL dan memicu 500.
     */
    public const MAX_TRACK_DURATION_MS = 72 * 60 * 60 * 1000;

    /**
     * Batas atas timestamp absolut titik GPS (epoch ms). Klien mengirim
     * `Date.now()`, bukan waktu relatif, sehingga batas 72 jam tidak boleh
     * diterapkan per titik. Angka ini (2100-01-01 UTC) hanya untuk menolak
     * nilai liar yang menghasilkan tanggal Carbon di luar jangkauan MySQL.
     */
    public const MAX_POINT_TIMESTAMP_MS = 4102444800000;

    /** Batas durasi yang sama dalam detik, untuk `elapsed_seconds`. */
    public const MAX_TRACK_DURATION_SECONDS = 72 * 60 * 60;

    public function __construct(private TrackMetricsCalculator $metrics) {}

    /**
     * @param  array<string, mixed>  $data  payload yang sudah lolos validasi
     */
    public function create(User $user, array $data): Workout
    {
        $points = $this->normalizePoints($data['points'] ?? []);
        $type = (string) $data['type'];
        $start = Carbon::parse($data['started_at']);
        $manual = count($points) < 2;

        // Mode tanpa GPS (mis. treadmill): jarak & durasi diisi manual.
        if ($manual) {
            $distanceMeters = round((float) ($data['manual_distance_meters'] ?? 0), 2);
            $movingSeconds = (int) ($data['manual_duration_seconds'] ?? 0);
            $elapsedSeconds = $movingSeconds;
            $analysis = null;
        } else {
            $analysis = $this->metrics->analyze($points, $type);
            $distanceMeters = $analysis['distance_meters'];
            $movingSeconds = $analysis['moving_seconds'];
            $elapsedSeconds = (int) ($data['elapsed_seconds']
                ?? round(($points[count($points) - 1]['t'] - $points[0]['t']) / 1000));
        }

        $elapsedSeconds = max(0, $elapsedSeconds);
        $end = $start->copy()->addSeconds($elapsedSeconds);

        $averagePace = $analysis['average_pace_seconds_per_km'] ?? null;

        if ($manual) {
            $averagePace = $distanceMeters > 0 && $movingSeconds > 0
                ? round($movingSeconds / ($distanceMeters / 1000.0), 1)
                : null;
        }

        return DB::transaction(function () use ($user, $data, $points, $type, $start, $end, $distanceMeters, $analysis, $averagePace): Workout {
            $workout = $user->workouts()->create([
                'type' => $type,
                'name' => $data['name'] ?? null,
                'description' => $data['description'] ?? null,
                'start_date' => $start,
                'end_date' => $end,
                'distance_meters' => $distanceMeters,
                'average_heart_rate' => $analysis['average_heart_rate'] ?? null,
                'max_heart_rate' => $analysis['max_heart_rate'] ?? null,
                'average_pace_seconds_per_km' => $averagePace,
                'elevation_gain_meters' => $analysis['elevation_gain_meters'] ?? null,
                'elevation_loss_meters' => $analysis['elevation_loss_meters'] ?? null,
                'source' => self::SOURCE,
                'visibility' => $data['visibility'] ?? ActivityVisibility::Private->value,
                // location_name sengaja null: reverse-geocoding butuh panggilan
                // jaringan yang tidak boleh menahan/menggagalkan penyimpanan.
                'location_name' => null,
            ]);

            $this->insertSamples($workout, $points, $analysis);
            $this->insertLaps($workout, $analysis['laps'] ?? []);

            return $workout;
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $rawPoints
     * @return list<array{t:int,lat:float,lng:float,alt:?float,hr:?float,cadence:?float}>
     */
    private function normalizePoints(array $rawPoints): array
    {
        $points = [];

        foreach ($rawPoints as $point) {
            $points[] = [
                't' => (int) $point['t'],
                'lat' => (float) $point['lat'],
                'lng' => (float) $point['lng'],
                'alt' => isset($point['alt']) ? (float) $point['alt'] : null,
                'hr' => isset($point['hr']) ? (float) $point['hr'] : null,
                'cadence' => isset($point['cadence']) ? (float) $point['cadence'] : null,
            ];
        }

        return $points;
    }

    /**
     * @param  list<array{t:int,lat:float,lng:float,alt:?float,hr:?float,cadence:?float}>  $points
     * @param  array<string, mixed>|null  $analysis
     */
    private function insertSamples(Workout $workout, array $points, ?array $analysis): void
    {
        if ($points === []) {
            return;
        }

        $now = now();
        $rows = [];

        foreach ($points as $index => $point) {
            $rows[] = [
                'workout_id' => $workout->id,
                'timestamp' => Carbon::createFromTimestampMs($point['t']),
                'heart_rate' => $point['hr'],
                'pace_seconds_per_km' => $analysis['point_paces'][$index] ?? null,
                'altitude_meters' => $point['alt'],
                'cadence' => $point['cadence'],
                'latitude' => $point['lat'],
                'longitude' => $point['lng'],
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        // Sama seperti ImportGarminData: insert berchunk agar 100k titik tidak
        // menjadi satu query raksasa.
        foreach (array_chunk($rows, 500) as $chunk) {
            WorkoutSample::insert($chunk);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $laps
     */
    private function insertLaps(Workout $workout, array $laps): void
    {
        if ($laps === []) {
            return;
        }

        $now = now();
        $rows = [];

        foreach ($laps as $lap) {
            $rows[] = [
                'workout_id' => $workout->id,
                'lap_index' => $lap['lap_index'],
                'start_time' => $lap['start_time'],
                'distance_meters' => $lap['distance_meters'],
                'duration_seconds' => $lap['duration_seconds'],
                'elevation_gain_meters' => $lap['elevation_gain_meters'],
                'elevation_loss_meters' => $lap['elevation_loss_meters'],
                'average_heart_rate' => $lap['average_heart_rate'],
                'max_heart_rate' => $lap['max_heart_rate'],
                'average_pace_seconds_per_km' => $lap['average_pace_seconds_per_km'],
                'calories' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        WorkoutLap::insert($rows);
    }
}
