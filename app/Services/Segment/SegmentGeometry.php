<?php

namespace App\Services\Segment;

/**
 * Matematika jarak yang dipakai bersama oleh SegmentMatcher dan
 * SegmentTrackBuilder. Rumus haversine-nya sengaja identik dengan
 * App\Services\Trail\TrailElevationProfileService supaya angka jarak pada
 * segment konsisten dengan halaman trail.
 */
class SegmentGeometry
{
    private const EARTH_RADIUS_METERS = 6371000;

    /**
     * @param  array{lat: float, lng: float}  $a
     * @param  array{lat: float, lng: float}  $b
     */
    public static function haversineMeters(array $a, array $b): float
    {
        $lat1 = deg2rad((float) $a['lat']);
        $lat2 = deg2rad((float) $b['lat']);
        $deltaLat = deg2rad((float) $b['lat'] - (float) $a['lat']);
        $deltaLng = deg2rad((float) $b['lng'] - (float) $a['lng']);

        $h = sin($deltaLat / 2) ** 2 + cos($lat1) * cos($lat2) * sin($deltaLng / 2) ** 2;
        $c = 2 * atan2(sqrt($h), sqrt(1 - $h));

        return self::EARTH_RADIUS_METERS * $c;
    }

    /**
     * Fraksi [0,1] posisi proyeksi titik $point pada ruas $a->$b, memakai
     * proyeksi equirectangular lokal (cukup akurat untuk skala puluhan meter).
     *
     * @param  array{lat: float, lng: float}  $a
     * @param  array{lat: float, lng: float}  $b
     * @param  array{lat: float, lng: float}  $point
     */
    public static function projectionFraction(array $a, array $b, array $point): float
    {
        $lngScale = cos(deg2rad((float) $a['lat']));

        $dx = (((float) $b['lng']) - ((float) $a['lng'])) * $lngScale;
        $dy = ((float) $b['lat']) - ((float) $a['lat']);
        $denominator = $dx * $dx + $dy * $dy;

        if ($denominator <= 0.0) {
            return 0.0;
        }

        $px = (((float) $point['lng']) - ((float) $a['lng'])) * $lngScale;
        $py = ((float) $point['lat']) - ((float) $a['lat']);

        return max(0.0, min(1.0, ($px * $dx + $py * $dy) / $denominator));
    }
}
