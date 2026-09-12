<?php

namespace App\Services\Segment;

use App\Models\Workout;
use App\Services\Trail\TrailElevationProfileService;

/**
 * Membangun geometri + statistik segment dari rentang indeks sampel sebuah
 * workout. Jarak memakai haversine bersama (SegmentGeometry); rata-rata
 * grade memakai TrailElevationProfileService agar angkanya konsisten dengan
 * halaman trail.
 */
class SegmentTrackBuilder
{
    public function __construct(private TrailElevationProfileService $elevation) {}

    /**
     * Sampel GPS sebuah workout, urut kronologis, sudah berbentuk array
     * (bukan model) supaya bisa langsung dipakai SegmentMatcher dan diuji
     * tanpa DB.
     *
     * @return list<array{lat: float, lng: float, altitude: ?float, timestamp: int, heart_rate: ?float, pace: ?float}>
     */
    public function pointsForWorkout(Workout $workout): array
    {
        return $workout->samples()
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->orderBy('timestamp')
            ->get(['latitude', 'longitude', 'altitude_meters', 'timestamp', 'heart_rate', 'pace_seconds_per_km'])
            ->map(fn ($sample) => [
                'lat' => (float) $sample->latitude,
                'lng' => (float) $sample->longitude,
                'altitude' => $sample->altitude_meters !== null ? (float) $sample->altitude_meters : null,
                'timestamp' => $sample->timestamp->getTimestamp(),
                'heart_rate' => $sample->heart_rate !== null ? (float) $sample->heart_rate : null,
                'pace' => $sample->pace_seconds_per_km !== null ? (float) $sample->pace_seconds_per_km : null,
            ])
            ->all();
    }

    /**
     * @param  list<array{lat: float, lng: float, altitude: ?float}>  $points
     * @return array{distance_meters: float, elevation_gain_meters: float, average_grade_percent: float, encoded_polyline: string, start_lat: float, start_lng: float, end_lat: float, end_lng: float}
     */
    public function build(array $points, int $startIndex, int $endIndex): array
    {
        $slice = array_slice($points, $startIndex, $endIndex - $startIndex + 1);

        $distance = 0.0;
        for ($i = 0; $i < count($slice) - 1; $i++) {
            $distance += SegmentGeometry::haversineMeters($slice[$i], $slice[$i + 1]);
        }

        $altitudePoints = [];
        $gain = 0.0;
        foreach ($slice as $point) {
            if ($point['altitude'] === null) {
                continue;
            }

            if ($altitudePoints !== []) {
                $gain += max(0.0, $point['altitude'] - $altitudePoints[count($altitudePoints) - 1]['altitude']);
            }

            $altitudePoints[] = ['lat' => $point['lat'], 'lng' => $point['lng'], 'altitude' => $point['altitude']];
        }

        $grade = 0.0;
        if (count($altitudePoints) > 1) {
            $grade = $this->elevation->buildProfile($altitudePoints)['avg_grade_percent'] ?? 0.0;
        }

        return [
            'distance_meters' => round($distance, 1),
            'elevation_gain_meters' => round($gain, 1),
            'average_grade_percent' => round((float) $grade, 1),
            'encoded_polyline' => PolylineCodec::encode(array_map(
                fn ($point) => ['lat' => $point['lat'], 'lng' => $point['lng']],
                $slice,
            )),
            'start_lat' => $slice[0]['lat'],
            'start_lng' => $slice[0]['lng'],
            'end_lat' => $slice[count($slice) - 1]['lat'],
            'end_lng' => $slice[count($slice) - 1]['lng'],
        ];
    }
}
