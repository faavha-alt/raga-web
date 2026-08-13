<?php

namespace App\Services\Trail;

use App\Models\Workout;
use Illuminate\Support\Carbon;

/**
 * "Moving time" isn't stored anywhere — Garmin's lap/workout duration is
 * total elapsed time, including any paused/stopped stretches. Derived here
 * the same way Step 6's HeartRateZoneService derives zone-time: sum gaps
 * between consecutive sample timestamps, skipping any gap over 60s (a
 * paused/resumed activity).
 */
class TrailMovingTimeCalculator
{
    private const MAX_SAMPLE_GAP_SECONDS = 60;

    public function movingSecondsForWorkout(Workout $workout): int
    {
        $samples = $workout->samples()
            ->orderBy('timestamp')
            ->get(['timestamp'])
            ->map(fn ($s) => ['timestamp' => $s->timestamp])
            ->all();

        return $this->movingSeconds($samples);
    }

    /**
     * Pure, DB-free — takes a chronologically-ordered list of
     * {timestamp: Carbon} and sums gaps of 60s or less between consecutive
     * samples.
     *
     * @param  list<array{timestamp: Carbon}>  $samples
     */
    public function movingSeconds(array $samples): int
    {
        $total = 0;

        for ($i = 0; $i < count($samples) - 1; $i++) {
            $gapSeconds = (int) round(abs($samples[$i]['timestamp']->diffInSeconds($samples[$i + 1]['timestamp'])));

            if ($gapSeconds > 0 && $gapSeconds <= self::MAX_SAMPLE_GAP_SECONDS) {
                $total += $gapSeconds;
            }
        }

        return $total;
    }
}
