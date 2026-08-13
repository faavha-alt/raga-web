<?php

namespace App\Services\Dashboard;

use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Simple rule-based insights: compares the last 7 days against the 7 days
 * before that for a handful of metrics already produced by
 * HealthTrendService. Needs at least 3 data points on each side of a
 * comparison before saying anything about it — sparse data yields fewer
 * insights, never a misleading one from 1-2 points.
 */
class InsightEngine
{
    private const MIN_POINTS_PER_WINDOW = 3;

    private const CHANGE_THRESHOLD_PERCENT = 8.0;

    public function __construct(private HealthTrendService $trends) {}

    /** @return list<string> */
    public function generate(User $user): array
    {
        $series = $this->trends->allSeries($user);
        $today = Carbon::today();
        $recentStart = $today->copy()->subDays(6);
        $previousStart = $today->copy()->subDays(13);
        $previousEnd = $today->copy()->subDays(7);

        $insights = [];

        foreach (['sleep', 'hrv', 'training_load', 'resting_hr'] as $metric) {
            $points = $series[$metric]['points'] ?? [];

            $recent = $this->averageInRange($points, $recentStart, $today);
            $previous = $this->averageInRange($points, $previousStart, $previousEnd);

            if ($recent === null || $previous === null || $previous['count'] === 0.0) {
                continue;
            }

            $insight = $this->compare($metric, $series[$metric]['label'], $recent['avg'], $previous['avg']);
            if ($insight) {
                $insights[] = $insight;
            }
        }

        return $insights;
    }

    /**
     * @param  list<array{date: string, value: float}>  $points
     * @return ?array{avg: float, count: float}
     */
    private function averageInRange(array $points, Carbon $start, Carbon $end): ?array
    {
        $inRange = array_filter($points, function ($p) use ($start, $end) {
            $date = Carbon::parse($p['date']);

            return $date->between($start, $end);
        });

        if (count($inRange) < self::MIN_POINTS_PER_WINDOW) {
            return null;
        }

        $values = array_column($inRange, 'value');

        return ['avg' => array_sum($values) / count($values), 'count' => (float) count($values)];
    }

    private function compare(string $metric, string $label, float $recentAvg, float $previousAvg): ?string
    {
        if ($previousAvg == 0.0) {
            return null;
        }

        $percentChange = (($recentAvg - $previousAvg) / abs($previousAvg)) * 100;

        if (abs($percentChange) < self::CHANGE_THRESHOLD_PERCENT) {
            return null;
        }

        $direction = $percentChange > 0 ? 'naik' : 'turun';

        return match ($metric) {
            'sleep' => sprintf('Durasi tidur %s %.0f%% dibanding 7 hari sebelumnya (%.1fh → %.1fh).', $direction, abs($percentChange), $previousAvg, $recentAvg),
            'hrv' => sprintf('HRV trennya sedang %s (%.0f%% dari 7 hari sebelumnya).', $direction, abs($percentChange)),
            'training_load' => sprintf('Training load %s signifikan minggu ini (%s%.0f%%).', $direction, $percentChange > 0 ? '+' : '', $percentChange),
            'resting_hr' => sprintf('Resting heart rate %s dibanding baseline 7 hari sebelumnya (%.0f → %.0f bpm).', $direction === 'naik' ? 'di atas' : 'di bawah', $previousAvg, $recentAvg),
            default => sprintf('%s %s %.0f%% dibanding periode sebelumnya.', $label, $direction, abs($percentChange)),
        };
    }
}
