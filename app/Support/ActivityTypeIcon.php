<?php

namespace App\Support;

/**
 * Shared activity-type -> icon/label mapping. Previously duplicated inline
 * in activities/index.blade.php and training/index.blade.php; extracted
 * here since Training's distribution view needs the same mapping as a
 * third consumer.
 */
class ActivityTypeIcon
{
    public static function icon(string $type): string
    {
        return match (true) {
            str_contains($type, 'run') || $type === 'walking' => '🏃',
            str_contains($type, 'bik') || str_contains($type, 'cycl') => '🚴',
            str_contains($type, 'swim') => '🏊',
            str_contains($type, 'strength') => '🏋️',
            default => '💪',
        };
    }

    public static function label(string $type): string
    {
        return ucwords(str_replace('_', ' ', $type));
    }
}
