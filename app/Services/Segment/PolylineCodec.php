<?php

namespace App\Services\Segment;

/**
 * Google encoded-polyline algorithm, precision 5 (1e-5 derajat ≈ 1,1 m).
 *
 * Dipakai untuk menyimpan geometri segment tanpa memuat seluruh
 * `workout_samples`; format ini juga yang dimengerti Leaflet Polyline via
 * plugin/decoder standar, dan cocok dengan contoh kanonik Google.
 *
 * Rumus encode/decode murni (tanpa DB) supaya bisa diuji round-trip.
 */
class PolylineCodec
{
    private const PRECISION = 100000;

    /**
     * @param  list<array{lat: float, lng: float}>  $points
     */
    public static function encode(array $points): string
    {
        $lastLat = 0;
        $lastLng = 0;
        $encoded = '';

        foreach ($points as $point) {
            $lat = (int) round(((float) $point['lat']) * self::PRECISION);
            $lng = (int) round(((float) $point['lng']) * self::PRECISION);

            $encoded .= self::encodeValue($lat - $lastLat);
            $encoded .= self::encodeValue($lng - $lastLng);

            $lastLat = $lat;
            $lastLng = $lng;
        }

        return $encoded;
    }

    /**
     * @return list<array{lat: float, lng: float}>
     */
    public static function decode(string $encoded): array
    {
        $points = [];
        $index = 0;
        $lat = 0;
        $lng = 0;
        $length = strlen($encoded);

        while ($index < $length) {
            $lat += self::decodeValue($encoded, $index);
            $lng += self::decodeValue($encoded, $index);

            $points[] = [
                'lat' => $lat / self::PRECISION,
                'lng' => $lng / self::PRECISION,
            ];
        }

        return $points;
    }

    private static function encodeValue(int $value): string
    {
        $value = $value < 0 ? ~($value << 1) : ($value << 1);

        $output = '';
        while ($value >= 0x20) {
            $output .= chr((0x20 | ($value & 0x1F)) + 63);
            $value >>= 5;
        }
        $output .= chr($value + 63);

        return $output;
    }

    private static function decodeValue(string $encoded, int &$index): int
    {
        $result = 0;
        $shift = 0;

        do {
            $byte = ord($encoded[$index++]) - 63;
            $result |= ($byte & 0x1F) << $shift;
            $shift += 5;
        } while ($byte >= 0x20);

        return ($result & 1) ? ~($result >> 1) : ($result >> 1);
    }
}
