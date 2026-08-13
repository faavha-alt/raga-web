<?php

namespace App\Services\Trail;

use App\Models\Workout;

/**
 * Elevation-over-distance profile + per-segment grade% for one trail
 * workout. WorkoutSample has altitude + lat/lng but no cumulative-distance
 * field, so distance between consecutive samples is derived via haversine
 * first.
 */
class TrailElevationProfileService
{
    private const EARTH_RADIUS_METERS = 6371000;

    /** Segments shorter than this are skipped when computing grade, to avoid divide-by-near-zero spikes. */
    private const MIN_SEGMENT_METERS = 2.0;

    /**
     * @return array{
     *     available: bool, points: list<array{value: float, label: string}>,
     *     avg_grade_percent: ?float, max_grade_percent: ?float, total_distance_meters: float,
     * }
     */
    public function profileFor(Workout $workout): array
    {
        $samples = $workout->samples()
            ->whereNotNull('altitude_meters')
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->orderBy('timestamp')
            ->get(['latitude', 'longitude', 'altitude_meters'])
            ->map(fn ($s) => [
                'lat' => (float) $s->latitude,
                'lng' => (float) $s->longitude,
                'altitude' => (float) $s->altitude_meters,
            ])
            ->all();

        return $this->buildProfile($samples);
    }

    /**
     * Pure, DB-free — takes an ordered list of {lat, lng, altitude} points
     * and returns the elevation-over-distance profile plus grade stats.
     *
     * @param  list<array{lat: float, lng: float, altitude: float}>  $points
     * @return array{
     *     available: bool, points: list<array{value: float, label: string}>,
     *     avg_grade_percent: ?float, max_grade_percent: ?float, total_distance_meters: float,
     * }
     */
    public function buildProfile(array $points): array
    {
        if (count($points) < 2) {
            return [
                'available' => false, 'points' => [], 'avg_grade_percent' => null,
                'max_grade_percent' => null, 'total_distance_meters' => 0.0,
            ];
        }

        $cumulativeDistance = 0.0;
        $chartPoints = [['value' => $points[0]['altitude'], 'label' => '0.00']];
        $grades = [];
        $maxGrade = null;

        for ($i = 0; $i < count($points) - 1; $i++) {
            $segmentDistance = $this->haversineMeters($points[$i], $points[$i + 1]);

            if ($segmentDistance < self::MIN_SEGMENT_METERS) {
                continue;
            }

            $deltaAltitude = $points[$i + 1]['altitude'] - $points[$i]['altitude'];
            $grade = ($deltaAltitude / $segmentDistance) * 100;

            $grades[] = $grade;
            $maxGrade = $maxGrade === null ? abs($grade) : max($maxGrade, abs($grade));

            $cumulativeDistance += $segmentDistance;
            $chartPoints[] = ['value' => $points[$i + 1]['altitude'], 'label' => number_format($cumulativeDistance / 1000, 2)];
        }

        return [
            'available' => true,
            'points' => $chartPoints,
            'avg_grade_percent' => $grades !== [] ? round(array_sum($grades) / count($grades), 1) : null,
            'max_grade_percent' => $maxGrade !== null ? round($maxGrade, 1) : null,
            'total_distance_meters' => round($cumulativeDistance, 1),
        ];
    }

    /** Great-circle distance between two {lat, lng} points, in meters. */
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
