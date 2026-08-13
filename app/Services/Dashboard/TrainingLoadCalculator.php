<?php

namespace App\Services\Dashboard;

use App\Models\ActivitySummary;

/**
 * Garmin's own "training load" metric was never fetched by the sync
 * pipeline, so there is no raw field for it. This computes a transparent,
 * real-data-only proxy — the day's active calories — rather than fabricate
 * something that looks like a Garmin metric. Always label this as an
 * estimate in the UI.
 */
class TrainingLoadCalculator
{
    public function forActivitySummary(?ActivitySummary $summary): float
    {
        return $summary?->active_calories ?? 0.0;
    }
}
