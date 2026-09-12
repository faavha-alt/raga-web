<?php

namespace App\Services\Recording;

use App\Services\Trail\TrailMovingTimeCalculator;
use Illuminate\Support\Carbon;

/**
 * Hitungan murni (tanpa DB) untuk trek GPS yang dikirim browser.
 *
 * Semua rumus noise-reduction sengaja dikumpulkan di sini supaya bisa diuji
 * terpisah dari persistensi. Sumber kebenaran "moving time" tetap
 * TrailMovingTimeCalculator milik domain Trail (aturan celah > 60 detik
 * dianggap jeda), bukan rumus baru.
 *
 * Aturan yang dipakai:
 *  - Jarak: haversine antar titik. Segmen yang menyiratkan laju mustahil
 *    (mis. lompatan GPS) DIBUANG, dan titik berikutnya diukur dari titik
 *    terakhir yang wajar — bukan dari titik glitch.
 *  - Elevasi: altitude dihaluskan (moving average) lalu hanya selisih yang
 *    melewati ambang yang dihitung, supaya noise barometrik/GPS tidak
 *    menggelembungkan elevation gain.
 */
class TrackMetricsCalculator
{
    /** Jari-jari bumi rata-rata (meter), cukup akurat untuk skala aktivitas. */
    private const EARTH_RADIUS_METERS = 6371000.0;

    /**
     * Ambang laju tidak wajar (meter/detik) per tipe aktivitas.
     *
     * Lari/walk/hike 12 m/s (~43 km/jam) sudah jauh di atas pelari tercepat;
     * sepeda 30 m/s (~108 km/jam) agar turunan cepat tidak ikut dibuang.
     */
    private const MAX_PLAUSIBLE_SPEED_MPS = [
        'running' => 12.0,
        'trail_running' => 12.0,
        'walking' => 6.0,
        'hiking' => 6.0,
        'cycling' => 30.0,
    ];

    private const DEFAULT_MAX_PLAUSIBLE_SPEED_MPS = 12.0;

    /** Perubahan altitude di bawah ini dianggap noise dan diabaikan. */
    private const ELEVATION_THRESHOLD_METERS = 3.0;

    /** Jumlah titik untuk moving average altitude (harus ganjil). */
    private const ELEVATION_SMOOTHING_WINDOW = 5;

    /** Celah antar titik di atas ini dianggap jeda (sama dengan TrailMovingTimeCalculator). */
    private const MAX_SAMPLE_GAP_SECONDS = 60;

    public function __construct(private TrailMovingTimeCalculator $movingTimeCalculator) {}

    /**
     * @param  list<array{t:int,lat:float,lng:float,alt:?float,hr:?float,cadence:?float}>  $points
     * @return array{
     *     distance_meters: float,
     *     moving_seconds: int,
     *     elevation_gain_meters: float,
     *     elevation_loss_meters: float,
     *     average_speed_mps: ?float,
     *     max_speed_mps: ?float,
     *     average_pace_seconds_per_km: ?float,
     *     average_heart_rate: ?float,
     *     max_heart_rate: ?float,
     *     average_cadence: ?float,
     *     point_paces: list<?float>,
     *     laps: list<array<string, mixed>>,
     * }
     */
    public function analyze(array $points, string $type): array
    {
        $points = array_values($points);
        $count = count($points);

        if ($count < 2) {
            return $this->emptyResult();
        }

        $maxSpeed = $this->maxPlausibleSpeed($type);
        $distance = 0.0;
        $maxSpeedSeen = 0.0;
        $pointPaces = array_fill(0, $count, null);
        $segments = array_fill(0, $count, null);

        // Indeks titik terakhir yang "wajar" — titik glitch tidak menggeser
        // acuan, sehingga segmen berikutnya kembali diukur dari jalur asli.
        $lastGood = 0;

        for ($i = 1; $i < $count; $i++) {
            $deltaSeconds = ($points[$i]['t'] - $points[$lastGood]['t']) / 1000.0;

            if ($deltaSeconds <= 0) {
                continue;
            }

            $segmentMeters = $this->haversineMeters(
                $points[$lastGood]['lat'],
                $points[$lastGood]['lng'],
                $points[$i]['lat'],
                $points[$i]['lng'],
            );

            $speed = $segmentMeters / $deltaSeconds;

            if ($speed > $maxSpeed) {
                // Lompatan GPS: buang segmen, tunggu titik berikutnya.
                continue;
            }

            $distance += $segmentMeters;
            $maxSpeedSeen = max($maxSpeedSeen, $speed);
            $segments[$i] = [
                'meters' => $segmentMeters,
                'seconds' => $deltaSeconds,
            ];
            $pointPaces[$i] = $segmentMeters > 0
                ? round($deltaSeconds / ($segmentMeters / 1000.0), 1)
                : null;
            $lastGood = $i;
        }

        [$elevationGain, $elevationLoss] = $this->elevationProfile(
            $this->smoothAltitudes(array_column($points, 'alt'))
        );

        $movingSeconds = $this->movingTimeCalculator->movingSeconds(
            array_map(
                static fn (array $point): array => ['timestamp' => Carbon::createFromTimestampMs($point['t'])],
                $points,
            )
        );

        return [
            'distance_meters' => round($distance, 2),
            'moving_seconds' => $movingSeconds,
            'elevation_gain_meters' => $elevationGain,
            'elevation_loss_meters' => $elevationLoss,
            'average_speed_mps' => $movingSeconds > 0 ? round($distance / $movingSeconds, 3) : null,
            'max_speed_mps' => $maxSpeedSeen > 0 ? round($maxSpeedSeen, 3) : null,
            'average_pace_seconds_per_km' => $this->paceSecondsPerKm($distance, $movingSeconds),
            'average_heart_rate' => $this->average(array_column($points, 'hr')),
            'max_heart_rate' => $this->maximum(array_column($points, 'hr')),
            'average_cadence' => $this->average(array_column($points, 'cadence')),
            'point_paces' => $pointPaces,
            'laps' => $this->buildLaps($points, $segments),
        ];
    }

    /**
     * Jarak lingkaran besar antara dua koordinat (meter).
     */
    public function haversineMeters(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $latFrom = deg2rad($lat1);
        $latTo = deg2rad($lat2);
        $latDelta = deg2rad($lat2 - $lat1);
        $lngDelta = deg2rad($lng2 - $lng1);

        $a = sin($latDelta / 2) ** 2
            + cos($latFrom) * cos($latTo) * sin($lngDelta / 2) ** 2;

        return 2 * self::EARTH_RADIUS_METERS * asin(min(1.0, sqrt($a)));
    }

    private function maxPlausibleSpeed(string $type): float
    {
        return self::MAX_PLAUSIBLE_SPEED_MPS[$type] ?? self::DEFAULT_MAX_PLAUSIBLE_SPEED_MPS;
    }

    /**
     * Rata-rata bergerak (moving average) untuk altitude; window ganjil
     * membuat puncak/lembah pendek tidak langsung dianggap perubahan nyata.
     *
     * @param  list<?float>  $altitudes
     * @return list<?float>
     */
    private function smoothAltitudes(array $altitudes): array
    {
        $half = intdiv(self::ELEVATION_SMOOTHING_WINDOW, 2);
        $count = count($altitudes);
        $smoothed = [];

        for ($i = 0; $i < $count; $i++) {
            if ($altitudes[$i] === null) {
                $smoothed[$i] = null;

                continue;
            }

            $sum = 0.0;
            $samples = 0;

            for ($j = max(0, $i - $half); $j <= min($count - 1, $i + $half); $j++) {
                if ($altitudes[$j] !== null) {
                    $sum += (float) $altitudes[$j];
                    $samples++;
                }
            }

            $smoothed[$i] = $samples > 0 ? $sum / $samples : null;
        }

        return $smoothed;
    }

    /**
     * Menjumlahkan naik/turun hanya saat selisih melewati ambang noise.
     *
     * @param  list<?float>  $altitudes
     * @return array{0: float, 1: float} [gain, loss]
     */
    private function elevationProfile(array $altitudes): array
    {
        $gain = 0.0;
        $loss = 0.0;
        $last = null;

        foreach ($altitudes as $altitude) {
            if ($altitude === null) {
                continue;
            }

            if ($last === null) {
                $last = $altitude;

                continue;
            }

            $delta = $altitude - $last;

            if (abs($delta) < self::ELEVATION_THRESHOLD_METERS) {
                continue;
            }

            if ($delta > 0) {
                $gain += $delta;
            } else {
                $loss += -$delta;
            }

            $last = $altitude;
        }

        return [round($gain, 2), round($loss, 2)];
    }

    /**
     * Lap per kilometer dari akumulasi jarak segmen yang wajar.
     *
     * @param  list<array<string, mixed>>  $points
     * @param  list<?array{meters:float,seconds:float}>  $segments
     * @return list<array<string, mixed>>
     */
    private function buildLaps(array $points, array $segments): array
    {
        $laps = [];
        $lapIndex = 1;
        $lapDistance = 0.0;
        $lapDuration = 0.0;
        $lapStart = $points[0]['t'];
        $lapHeartRates = [];
        $lapAltitudes = [];

        for ($i = 1; $i < count($points); $i++) {
            if ($segments[$i] === null) {
                continue;
            }

            $lapDistance += $segments[$i]['meters'];
            $lapDuration += $segments[$i]['seconds'] <= self::MAX_SAMPLE_GAP_SECONDS ? $segments[$i]['seconds'] : 0;

            if ($points[$i]['hr'] !== null) {
                $lapHeartRates[] = (float) $points[$i]['hr'];
            }

            if ($points[$i]['alt'] !== null) {
                $lapAltitudes[] = (float) $points[$i]['alt'];
            }

            if ($lapDistance < 1000.0) {
                continue;
            }

            $laps[] = $this->lapRow($lapIndex++, $lapStart, $points[$i]['t'], $lapDistance, $lapDuration, $lapHeartRates, $lapAltitudes);
            $lapDistance = 0.0;
            $lapDuration = 0.0;
            $lapStart = $points[$i]['t'];
            $lapHeartRates = [];
            $lapAltitudes = [];
        }

        if ($lapDistance > 0) {
            $laps[] = $this->lapRow($lapIndex, $lapStart, $points[count($points) - 1]['t'], $lapDistance, $lapDuration, $lapHeartRates, $lapAltitudes);
        }

        return $laps;
    }

    /**
     * @param  list<float>  $heartRates
     * @param  list<float>  $altitudes
     * @return array<string, mixed>
     */
    private function lapRow(int $index, int $startMs, int $endMs, float $distance, float $duration, array $heartRates, array $altitudes): array
    {
        [$gain, $loss] = $this->elevationProfile($this->smoothAltitudes($altitudes));

        return [
            'lap_index' => $index,
            'start_time' => Carbon::createFromTimestampMs($startMs),
            'distance_meters' => round($distance, 2),
            'duration_seconds' => round($duration),
            'elevation_gain_meters' => $gain,
            'elevation_loss_meters' => $loss,
            'average_heart_rate' => $this->average($heartRates),
            'max_heart_rate' => $this->maximum($heartRates),
            'average_pace_seconds_per_km' => $this->paceSecondsPerKm($distance, (int) round($duration)),
        ];
    }

    private function paceSecondsPerKm(float $distanceMeters, int $seconds): ?float
    {
        if ($distanceMeters <= 0 || $seconds <= 0) {
            return null;
        }

        return round($seconds / ($distanceMeters / 1000.0), 1);
    }

    /**
     * @param  list<?float>  $values
     */
    private function average(array $values): ?float
    {
        $numbers = array_values(array_filter($values, static fn ($value): bool => $value !== null));

        if ($numbers === []) {
            return null;
        }

        return round(array_sum($numbers) / count($numbers), 1);
    }

    /**
     * @param  list<?float>  $values
     */
    private function maximum(array $values): ?float
    {
        $numbers = array_values(array_filter($values, static fn ($value): bool => $value !== null));

        return $numbers === [] ? null : round((float) max($numbers), 1);
    }

    /**
     * @return array{
     *     distance_meters: float, moving_seconds: int,
     *     elevation_gain_meters: float, elevation_loss_meters: float,
     *     average_speed_mps: ?float, max_speed_mps: ?float,
     *     average_pace_seconds_per_km: ?float,
     *     average_heart_rate: ?float, max_heart_rate: ?float, average_cadence: ?float,
     *     point_paces: list<?float>, laps: list<array<string, mixed>>,
     * }
     */
    private function emptyResult(): array
    {
        return [
            'distance_meters' => 0.0,
            'moving_seconds' => 0,
            'elevation_gain_meters' => 0.0,
            'elevation_loss_meters' => 0.0,
            'average_speed_mps' => null,
            'max_speed_mps' => null,
            'average_pace_seconds_per_km' => null,
            'average_heart_rate' => null,
            'max_heart_rate' => null,
            'average_cadence' => null,
            'point_paces' => [],
            'laps' => [],
        ];
    }
}
