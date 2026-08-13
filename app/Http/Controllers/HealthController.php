<?php

namespace App\Http\Controllers;

use App\Services\Health\DailySummaryService;
use App\Services\Health\MetricSeriesService;
use App\Services\Health\PersonalBaselineService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HealthController extends Controller
{
    private const OVERVIEW_METRICS = [
        'resting_hr', 'avg_hr', 'max_hr', 'hrv', 'stress',
        'body_battery_net', 'respiration', 'spo2', 'steps', 'calories',
    ];

    public function __construct(
        private MetricSeriesService $metricSeries,
        private PersonalBaselineService $baseline,
        private DailySummaryService $dailySummary,
    ) {}

    public function overview(Request $request): View
    {
        $user = $request->user();

        $today = collect(self::OVERVIEW_METRICS)->mapWithKeys(function (string $metric) use ($user) {
            $latest = $this->metricSeries->latest($user, $metric);

            return [$metric => [
                'meta' => $this->metricSeries->meta($metric),
                'value' => $latest['value'] ?? null,
                'baseline' => $this->baseline->compute($user, $metric),
            ]];
        });

        return view('health.overview', [
            'today' => $today,
            'disclaimer' => PersonalBaselineService::DISCLAIMER,
        ]);
    }

    public function heart(Request $request): View
    {
        return $this->detailPage($request, ['resting_hr', 'avg_hr', 'max_hr', 'hrv'], 'health.heart');
    }

    public function stress(Request $request): View
    {
        return $this->detailPage($request, ['stress'], 'health.stress');
    }

    public function bodyBattery(Request $request): View
    {
        return $this->detailPage($request, ['body_battery_charged', 'body_battery_drained', 'body_battery_net'], 'health.body-battery');
    }

    public function dailyMetrics(Request $request): View
    {
        return $this->detailPage($request, ['respiration', 'spo2', 'steps', 'calories'], 'health.daily-metrics');
    }

    private function detailPage(Request $request, array $metrics, string $view): View
    {
        $user = $request->user();

        $series = collect($metrics)->mapWithKeys(fn (string $metric) => [
            $metric => $this->metricSeries->meta($metric) + ['points' => $this->metricSeries->seriesFor($user, $metric, 365)],
        ])->all();

        $baselines = collect($metrics)->mapWithKeys(
            fn (string $metric) => [$metric => $this->baseline->compute($user, $metric)]
        )->all();

        return view($view, [
            'series' => $series,
            'baselines' => $baselines,
            'metrics' => $metrics,
            'dailyRows' => $this->dailySummary->forMetrics($user, $metrics),
            'disclaimer' => PersonalBaselineService::DISCLAIMER,
        ]);
    }
}
