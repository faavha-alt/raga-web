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
}
