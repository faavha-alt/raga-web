<?php

namespace App\Services\Analytics;

/**
 * The 5 "X vs Y" relationship pairs as data, not 5 near-duplicate
 * controller methods/views. training-load-performance pairs Training Load
 * against running pace — pace is the most granular already-modeled
 * "performance" proxy available (RunningPerformanceCalculator only
 * produces one 30-day snapshot, not a daily series, so it can't be paired
 * point-by-point).
 */
class RelationshipCatalog
{
    private const DEFINITIONS = [
        'training-recovery' => ['title' => 'Training vs Recovery', 'key_a' => 'training_load', 'key_b' => 'recovery_score'],
        'pace-heart-rate' => ['title' => 'Pace vs Heart Rate', 'key_a' => 'running_pace', 'key_b' => 'running_avg_hr'],
        'training-load-performance' => ['title' => 'Training Load vs Performance', 'key_a' => 'training_load', 'key_b' => 'running_pace'],
        // Sleep & HRV pairs sengaja tidak disertakan — mayoritas pengguna tidak
        // memakai jam saat tidur, jadi kedua seri itu hampir selalu kosong.
    ];

    /** @return array{slug: string, title: string, key_a: string, key_b: string}|null */
    public function find(string $slug): ?array
    {
        if (! isset(self::DEFINITIONS[$slug])) {
            return null;
        }

        return ['slug' => $slug, ...self::DEFINITIONS[$slug]];
    }

    /** @return list<array{slug: string, title: string, key_a: string, key_b: string}> */
    public function all(): array
    {
        return collect(self::DEFINITIONS)
            ->map(fn ($definition, $slug) => ['slug' => $slug, ...$definition])
            ->values()
            ->all();
    }

    /** @return list<string> */
    public function slugs(): array
    {
        return array_keys(self::DEFINITIONS);
    }
}
