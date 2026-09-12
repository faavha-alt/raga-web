<?php

namespace App\Services\Segment;

use Illuminate\Support\Carbon;

/**
 * Pencocokan (matching) sebuah workout terhadap segment.
 *
 * Algoritma (murni, tanpa DB — diuji di tests/Unit/SegmentMatcherTest.php):
 *   1. Telusuri sampel maju secara kronologis, temukan sampel PERTAMA
 *      dalam radius `radiusMeters` dari titik awal segment.
 *   2. Dari indeks itu, telusuri maju lagi dan temukan sampel PERTAMA dalam
 *      radius dari titik akhir segment. Karena pencariannya selalu SETELAH
 *      indeks awal, urutan terbalik (akhir dulu, baru awal) otomatis gagal.
 *   3. Tolak lintasan yang tidak wajar (lompatan GPS): jika pada rentang
 *      yang cocok ada pasangan sampel berurutan dengan kecepatan melebihi
 *      MAX_PLAUSIBLE_SPEED_MPS, hasil dianggap glitch dan tidak dicatat.
 *   4. Hitung waktu lintas awal/akhir secara terinterpolasi (proyeksi tegak
 *      lurus ke ruas terdekat) agar elapsed_seconds presisi, bukan sekadar
 *      selisih dua sampel.
 */
class SegmentMatcher
{
    public const DEFAULT_RADIUS_METERS = 25.0;

    /**
     * Batas atas kecepatan yang masih masuk akal antar dua sampel GPS
     * berurutan (45 m/s ≈ 162 km/jam — di atas ini hampir pasti lompatan
     * sinyal, bukan gerakan nyata).
     */
    public const MAX_PLAUSIBLE_SPEED_MPS = 45.0;

    /**
     * @param  array{start_lat: float, start_lng: float, end_lat: float, end_lng: float}  $segment
     * @param  list<array{lat: float, lng: float, timestamp: int|float|Carbon}>  $samples  urut kronologis
     * @return array{start_index: int, end_index: int, start_timestamp: float, end_timestamp: float, elapsed_seconds: float}|null
     */
    public function match(array $segment, array $samples, float $radiusMeters = self::DEFAULT_RADIUS_METERS): ?array
    {
        $count = count($samples);

        if ($count < 2) {
            return null;
        }

        $startTarget = ['lat' => (float) $segment['start_lat'], 'lng' => (float) $segment['start_lng']];
        $endTarget = ['lat' => (float) $segment['end_lat'], 'lng' => (float) $segment['end_lng']];

        $startIndex = $this->firstSampleWithin($samples, 0, $startTarget, $radiusMeters);

        if ($startIndex === null) {
            return null;
        }

        $endIndex = $this->firstSampleWithin($samples, $startIndex + 1, $endTarget, $radiusMeters);

        if ($endIndex === null) {
            return null;
        }

        if (! $this->spanIsPlausible($samples, $startIndex, $endIndex)) {
            return null;
        }

        $startTimestamp = $this->interpolatedTimestamp($samples, $startIndex, $startTarget);
        $endTimestamp = $this->interpolatedTimestamp($samples, $endIndex, $endTarget);

        if ($endTimestamp <= $startTimestamp) {
            return null;
        }

        return [
            'start_index' => $startIndex,
            'end_index' => $endIndex,
            'start_timestamp' => $startTimestamp,
            'end_timestamp' => $endTimestamp,
            'elapsed_seconds' => $endTimestamp - $startTimestamp,
        ];
    }

    /**
     * Kompatibilitas tipe aktivitas memakai logika substring yang sama
     * dengan App\Support\ActivityTypeIcon (running vs trail_running cocok,
     * cycling tidak). Untuk tipe yang tidak dikenali keluarga ikonnya,
     * tipe itu sendiri yang dipakai sebagai keluarga agar dua aktivitas
     * berbeda (mis. hiking vs yoga) tidak saling dianggap cocok.
     */
    public static function typesCompatible(string $segmentType, string $workoutType): bool
    {
        return self::activityFamily($segmentType) === self::activityFamily($workoutType);
    }

    public static function activityFamily(string $type): string
    {
        return match (true) {
            str_contains($type, 'run') || $type === 'walking' => 'run',
            str_contains($type, 'bik') || str_contains($type, 'cycl') => 'bike',
            str_contains($type, 'swim') => 'swim',
            str_contains($type, 'strength') => 'strength',
            default => $type,
        };
    }

    /**
     * @param  list<array{lat: float, lng: float, timestamp: int|float|Carbon}>  $samples
     * @param  array{lat: float, lng: float}  $target
     */
    private function firstSampleWithin(array $samples, int $from, array $target, float $radiusMeters): ?int
    {
        for ($i = $from; $i < count($samples); $i++) {
            if (SegmentGeometry::haversineMeters($target, $this->point($samples[$i])) <= $radiusMeters) {
                return $i;
            }
        }

        return null;
    }

    /**
     * @param  list<array{lat: float, lng: float, timestamp: int|float|Carbon}>  $samples
     */
    private function spanIsPlausible(array $samples, int $startIndex, int $endIndex): bool
    {
        for ($i = $startIndex; $i < $endIndex; $i++) {
            $deltaSeconds = $this->timestampOf($samples[$i + 1]) - $this->timestampOf($samples[$i]);

            if ($deltaSeconds <= 0) {
                return false;
            }

            $speed = SegmentGeometry::haversineMeters($this->point($samples[$i]), $this->point($samples[$i + 1])) / $deltaSeconds;

            if ($speed > self::MAX_PLAUSIBLE_SPEED_MPS) {
                return false;
            }
        }

        return true;
    }

    /**
     * Waktu lintas terinterpolasi: proyeksikan titik target ke ruas sampel di
     * sekitar $index lalu hitung waktunya secara linear.
     *
     * @param  list<array{lat: float, lng: float, timestamp: int|float|Carbon}>  $samples
     * @param  array{lat: float, lng: float}  $target
     */
    private function interpolatedTimestamp(array $samples, int $index, array $target): float
    {
        $best = $this->timestampOf($samples[$index]);
        $bestDistance = SegmentGeometry::haversineMeters($target, $this->point($samples[$index]));

        foreach ([[$index - 1, $index], [$index, $index + 1]] as [$a, $b]) {
            if ($a < 0 || $b >= count($samples)) {
                continue;
            }

            $pointA = $this->point($samples[$a]);
            $pointB = $this->point($samples[$b]);
            $fraction = SegmentGeometry::projectionFraction($pointA, $pointB, $target);

            $projected = [
                'lat' => $pointA['lat'] + ($pointB['lat'] - $pointA['lat']) * $fraction,
                'lng' => $pointA['lng'] + ($pointB['lng'] - $pointA['lng']) * $fraction,
            ];

            $distance = SegmentGeometry::haversineMeters($target, $projected);

            if ($distance < $bestDistance) {
                $bestDistance = $distance;
                $best = $this->timestampOf($samples[$a])
                    + ($this->timestampOf($samples[$b]) - $this->timestampOf($samples[$a])) * $fraction;
            }
        }

        return $best;
    }

    /**
     * @param  array{lat: float, lng: float}  $sample
     * @return array{lat: float, lng: float}
     */
    private function point(array $sample): array
    {
        return ['lat' => (float) $sample['lat'], 'lng' => (float) $sample['lng']];
    }

    private function timestampOf(array $sample): float
    {
        $timestamp = $sample['timestamp'];

        return $timestamp instanceof Carbon
            ? (float) $timestamp->getTimestamp()
            : (float) $timestamp;
    }
}
