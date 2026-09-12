<?php

namespace App\Services\Activity;

use App\Models\Workout;

class ActivityDetailService
{
    /**
     * Shapes workout_samples into the 3 charts the detail page can honestly
     * show (heart rate, pace, elevation) — whichever of these actually has
     * data for this particular workout; a series with no samples is
     * omitted rather than rendered empty.
     *
     * @return array<string, array{label: string, unit: string, color: string, decimals: int, points: list<array{value: float, label: string}>}>
     */
    public function chartsFor(Workout $workout): array
    {
        $samples = $workout->samples()->orderBy('timestamp')->get();
        $startTimestamp = $workout->start_date->getTimestamp();

        $charts = [
            'heart_rate' => ['label' => 'Heart Rate', 'unit' => 'bpm', 'color' => '#e34948', 'decimals' => 0],
            'pace' => ['label' => 'Pace', 'unit' => 'min/km', 'color' => '#2a78d6', 'decimals' => 1],
            'elevation' => ['label' => 'Elevation', 'unit' => 'm', 'color' => '#1baf7a', 'decimals' => 0],
        ];

        $points = ['heart_rate' => [], 'pace' => [], 'elevation' => []];

        foreach ($samples as $sample) {
            $elapsedLabel = $this->formatElapsed(abs($sample->timestamp->getTimestamp() - $startTimestamp));

            if ($sample->heart_rate !== null) {
                $points['heart_rate'][] = ['value' => (float) $sample->heart_rate, 'label' => $elapsedLabel];
            }

            if ($sample->pace_seconds_per_km !== null && $sample->pace_seconds_per_km > 0) {
                $points['pace'][] = ['value' => round($sample->pace_seconds_per_km / 60, 2), 'label' => $elapsedLabel];
            }

            if ($sample->altitude_meters !== null) {
                $points['elevation'][] = ['value' => (float) $sample->altitude_meters, 'label' => $elapsedLabel];
            }
        }

        foreach ($charts as $key => &$chart) {
            $chart['points'] = $points[$key];
        }

        return $charts;
    }

    private function formatElapsed(int $seconds): string
    {
        $minutes = intdiv($seconds, 60);
        $secs = $seconds % 60;

        return sprintf('%d:%02d', $minutes, $secs);
    }

    /** @return list<array{lat: float, lng: float}> empty when this workout has no GPS samples */
    public function routePoints(Workout $workout): array
    {
        return $workout->samples()
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->orderBy('timestamp')
            ->get(['latitude', 'longitude'])
            ->map(fn ($s) => ['lat' => (float) $s->latitude, 'lng' => (float) $s->longitude])
            ->all();
    }

    /** Jarak antar dua titik (meter). */
    private const EARTH_RADIUS_METERS = 6371000;

    /** Gap antar-sampel di atas ini dianggap jeda (pause) dan tidak menambah jarak/waktu. */
    private const MAX_GAP_SECONDS = 120;

    /** Segmen lebih pendek dari ini tidak dihitung grade-nya (hindari lonjakan bagi-nol). */
    private const MIN_SEGMENT_METERS = 2.0;

    /** Panjang satu split (meter). */
    private const SPLIT_METERS = 1000.0;

    /**
     * Profil elevasi terhadap jarak, lengkap dengan grade per segmen — dipakai
     * grafik berband gradien. Jarak diturunkan dari GPS (haversine); untuk
     * aktivitas tanpa GPS dipakai pace per sampel sebagai cadangan, supaya
     * treadmill/indoor pun tetap punya bentuk profil.
     *
     * @return array{
     *     available: bool,
     *     points: list<array{distance_km: float, elevation: float, grade: float}>,
     *     gain_meters: float, loss_meters: float,
     *     min_elevation: ?float, max_elevation: ?float, total_distance_km: float,
     * }
     */
    public function elevationProfileWithGrade(Workout $workout): array
    {
        $samples = $this->sampleRows($workout);

        $empty = [
            'available' => false, 'points' => [], 'gain_meters' => 0.0, 'loss_meters' => 0.0,
            'min_elevation' => null, 'max_elevation' => null, 'total_distance_km' => 0.0,
        ];

        if (count($samples) < 2) {
            return $empty;
        }

        $points = [];
        $cumulative = 0.0;
        $gain = 0.0;
        $loss = 0.0;

        foreach ($samples as $index => $sample) {
            if ($sample['altitude'] === null) {
                continue;
            }

            $distance = 0.0;
            $grade = 0.0;

            if ($points !== []) {
                $previous = $samples[$index - 1];
                $distance = $this->segmentDistance($previous, $sample);

                if ($distance > 0 && $previous['altitude'] !== null) {
                    $gain += max(0, $sample['altitude'] - $previous['altitude']);
                    $loss += max(0, $previous['altitude'] - $sample['altitude']);

                    if ($distance >= self::MIN_SEGMENT_METERS) {
                        $grade = (($sample['altitude'] - $previous['altitude']) / $distance) * 100;
                    }
                }

                $cumulative += $distance;
            }

            $points[] = [
                'distance_km' => round($cumulative / 1000, 3),
                'elevation' => (float) $sample['altitude'],
                'grade' => round($grade, 1),
            ];
        }

        if (count($points) < 2) {
            return $empty;
        }

        $elevations = array_column($points, 'elevation');

        return [
            'available' => true,
            'points' => $points,
            'gain_meters' => round($gain),
            'loss_meters' => round($loss),
            'min_elevation' => min($elevations),
            'max_elevation' => max($elevations),
            'total_distance_km' => round($cumulative / 1000, 2),
        ];
    }

    /**
     * Split per kilometer: durasi, pace, elevasi naik/turun, grade rata-rata,
     * dan HR rata-rata (hanya bila $withHeartRate — HR = data kesehatan milik
     * pemilik aktivitas). Diberi tag "fastest", "climb", atau "descent" bila
     * jumlah split cukup banyak.
     *
     * @return list<array{
     *     index: int, distance_km: float, duration_seconds: int, pace_seconds_per_km: ?int,
     *     elevation_gain: float, elevation_loss: float, avg_grade: float,
     *     avg_heart_rate: ?float, tag: ?string,
     * }>
     */
    public function splitsFor(Workout $workout, bool $withHeartRate): array
    {
        $samples = $this->sampleRows($workout);

        if (count($samples) < 2) {
            return [];
        }

        $splits = [];
        $samplesInSplit = [$samples[0]];
        $splitDistance = 0.0;
        $splitSeconds = 0;
        $lastIndex = count($samples) - 1;

        for ($i = 0; $i < $lastIndex; $i++) {
            $current = $samples[$i];
            $next = $samples[$i + 1];

            $gap = $next['seconds'] - $current['seconds'];

            if ($gap <= 0 || $gap > self::MAX_GAP_SECONDS) {
                // Jeda: mulai segmen baru tanpa menambah jarak.
                $samplesInSplit = [$next];

                continue;
            }

            $distance = $this->segmentDistance($current, $next);

            if ($distance === null) {
                continue;
            }

            $splitDistance += $distance;
            $splitSeconds += $gap;
            $samplesInSplit[] = $next;

            // `$splitDistance` direset setiap kali split ditutup, jadi ambangnya
            // selalu satu kilometer — bukan akumulasi jarak total.
            while ($splitDistance >= self::SPLIT_METERS) {
                $splits[] = $this->buildSplit(count($splits) + 1, $splitDistance, $splitSeconds, $samplesInSplit, $withHeartRate);
                $samplesInSplit = [$next];
                $splitDistance = 0.0;
                $splitSeconds = 0;
            }
        }

        // Sisa terakhir (mis. 3,4 km): tampilkan sebagai split parsial selama
        // jaraknya bermakna.
        if ($splitDistance >= 100.0 && $samplesInSplit !== []) {
            $splits[] = $this->buildSplit(count($splits) + 1, $splitDistance, $splitSeconds, $samplesInSplit, $withHeartRate);
        }

        return $this->tagSplits($splits);
    }

    /**
     * @param  list<array<string, mixed>>  $samples
     * @return array<string, mixed>
     */
    private function buildSplit(int $index, float $distanceMeters, int $seconds, array $samples, bool $withHeartRate): array
    {
        $gain = 0.0;
        $loss = 0.0;

        for ($i = 0; $i < count($samples) - 1; $i++) {
            $delta = ($samples[$i + 1]['altitude'] ?? 0.0) - ($samples[$i]['altitude'] ?? 0.0);
            $gain += max(0, $delta);
            $loss += max(0, -$delta);
        }

        $pace = $distanceMeters > 0 ? (int) round($seconds / ($distanceMeters / 1000)) : null;

        $heartRates = $withHeartRate
            ? array_values(array_filter(array_column($samples, 'heart_rate'), fn ($hr) => $hr !== null))
            : [];

        return [
            'index' => $index,
            'distance_km' => round($distanceMeters / 1000, 2),
            'duration_seconds' => $seconds,
            'pace_seconds_per_km' => $pace,
            'elevation_gain' => round($gain),
            'elevation_loss' => round($loss),
            'avg_grade' => $distanceMeters > 0 ? round(($gain - $loss) / $distanceMeters * 100, 1) : 0.0,
            'avg_heart_rate' => $heartRates !== [] ? round(array_sum($heartRates) / count($heartRates)) : null,
            'tag' => null,
        ];
    }

    /**
     * Beri tag pada paling banyak tiga split. Hanya dilakukan bila split ≥ 3,
     * supaya satu-satunya split tidak otomatis diberi label "tercepat".
     *
     * @param  list<array<string, mixed>>  $splits
     * @return list<array<string, mixed>>
     */
    private function tagSplits(array $splits): array
    {
        if (count($splits) < 3) {
            return $splits;
        }

        $fullSplits = array_filter($splits, fn (array $split): bool => $split['distance_km'] >= 0.95 && $split['pace_seconds_per_km'] !== null);

        if ($fullSplits !== []) {
            $fastest = array_reduce(
                $fullSplits,
                fn ($carry, $split) => $carry === null || $split['pace_seconds_per_km'] < $carry['pace_seconds_per_km'] ? $split : $carry,
            );
            $splits[$fastest['index'] - 1]['tag'] = 'fastest';
        }

        $climb = array_reduce($splits, fn ($carry, $split) => $carry === null || $split['elevation_gain'] > $carry['elevation_gain'] ? $split : $carry);

        if ($climb !== null && $climb['elevation_gain'] > 0 && $climb['tag'] === null) {
            $splits[$climb['index'] - 1]['tag'] = 'climb';
        }

        $descent = array_reduce($splits, fn ($carry, $split) => $carry === null || $split['elevation_loss'] > $carry['elevation_loss'] ? $split : $carry);

        if ($descent !== null && $descent['elevation_loss'] > 0 && $descent['tag'] === null) {
            $splits[$descent['index'] - 1]['tag'] = 'descent';
        }

        return $splits;
    }

    /**
     * Sampel mentah yang dibutuhkan split/profil, sudah diurutkan waktu.
     *
     * @return list<array{seconds: int, heart_rate: ?float, pace: ?float, altitude: ?float, lat: ?float, lng: ?float}>
     */
    private function sampleRows(Workout $workout): array
    {
        return $workout->samples()
            ->orderBy('timestamp')
            ->get(['timestamp', 'heart_rate', 'pace_seconds_per_km', 'altitude_meters', 'latitude', 'longitude'])
            ->map(fn ($sample) => [
                'seconds' => $sample->timestamp->getTimestamp(),
                'heart_rate' => $sample->heart_rate !== null ? (float) $sample->heart_rate : null,
                'pace' => $sample->pace_seconds_per_km !== null ? (float) $sample->pace_seconds_per_km : null,
                'altitude' => $sample->altitude_meters !== null ? (float) $sample->altitude_meters : null,
                'lat' => $sample->latitude !== null ? (float) $sample->latitude : null,
                'lng' => $sample->longitude !== null ? (float) $sample->longitude : null,
            ])
            ->all();
    }

    /**
     * Jarak satu segmen (meter): haversine bila kedua titik punya GPS, kalau
     * tidak memakai pace sampel (detik per km) sebagai cadangan. Null bila
     * tidak ada cara menghitungnya.
     *
     * @param  array<string, mixed>  $a
     * @param  array<string, mixed>  $b
     */
    private function segmentDistance(array $a, array $b): ?float
    {
        if ($a['lat'] !== null && $a['lng'] !== null && $b['lat'] !== null && $b['lng'] !== null) {
            return $this->haversineMeters($a, $b);
        }

        $pace = $b['pace'] ?? $a['pace'];
        $seconds = $b['seconds'] - $a['seconds'];

        if ($pace !== null && $pace > 0 && $seconds > 0) {
            return $seconds * (1000 / $pace);
        }

        return null;
    }

    /** @param array<string, mixed> $a @param array<string, mixed> $b */
    private function haversineMeters(array $a, array $b): float
    {
        $lat1 = deg2rad($a['lat']);
        $lat2 = deg2rad($b['lat']);
        $deltaLat = deg2rad($b['lat'] - $a['lat']);
        $deltaLng = deg2rad($b['lng'] - $a['lng']);

        $h = sin($deltaLat / 2) ** 2 + cos($lat1) * cos($lat2) * sin($deltaLng / 2) ** 2;
        $c = 2 * atan2(sqrt($h), sqrt(1 - $h));

        return self::EARTH_RADIUS_METERS * $c;
    }
}
