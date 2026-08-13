<?php

namespace App\Services\Analytics;

use App\Models\User;
use App\Services\Health\MetricSeriesService;
use App\Services\Running\RunningSeriesService;
use Illuminate\Support\Carbon;

/**
 * The one place that knows how to fetch any metric this module correlates,
 * dispatching to whichever series source already owns that data — so
 * CorrelationService stays pure and every relationship page pulls data the
 * same way.
 */
class AnalyticsSeriesGateway
{
    private const META = [
        'recovery_score' => ['label' => 'Recovery Score', 'unit' => '', 'color' => '#4a3aa7', 'decimals' => 0],
    ];

    public function __construct(
        private MetricSeriesService $metricSeries,
        private RunningSeriesService $runningSeries,
    ) {}

    public function meta(string $key): array
    {
        return match ($key) {
            'running_pace' => $this->runningSeries->meta('pace'),
            'running_avg_hr' => $this->runningSeries->meta('avg_hr'),
            default => self::META[$key] ?? $this->metricSeries->meta($key),
        };
    }

    /** @return list<array{date: string, value: float}> */
    public function seriesFor(User $user, string $key, int $days): array
    {
        return match ($key) {
            'hrv', 'sleep', 'training_load' => $this->metricSeries->seriesFor($user, $key, $days),
            'recovery_score' => $this->recoveryScoreSeries($user, $days),
            'running_pace' => $this->runningSeries->seriesFor($user, 'pace', $days),
            'running_avg_hr' => $this->runningSeries->seriesFor($user, 'avg_hr', $days),
            default => [],
        };
    }

    /** @return list<array{date: string, value: float}> */
    private function recoveryScoreSeries(User $user, int $days): array
    {
        $since = Carbon::today()->subDays($days - 1);

        return $user->recoveryScores()
            ->where('date', '>=', $since)
            ->orderBy('date')
            ->get()
            ->map(fn ($r) => ['date' => $r->date->toDateString(), 'value' => (float) $r->score])
            ->values()
            ->all();
    }
}
