<?php

namespace App\Http\Controllers;

use App\Services\Health\MetricSeriesService;
use App\Services\Running\RunningPerformanceCalculator;
use App\Services\Running\RunningPerformanceGateway;
use App\Services\Running\RunningSeriesService;
use App\Services\Training\TrainingConsistencyService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class RunningController extends Controller
{
    private const TYPE = 'running';

    public function __construct(
        private RunningSeriesService $series,
        private RunningPerformanceGateway $performanceGateway,
        private RunningPerformanceCalculator $performanceCalculator,
        private TrainingConsistencyService $consistencyService,
        private MetricSeriesService $metricSeries,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $today = Carbon::today();

        $weekTotals = $this->series->totalsForRange($user, $today->copy()->subDays(6), $today);

        $performance = $this->performanceCalculator->calculate(
            $this->performanceGateway->runsInWindow($user, $today),
            $this->performanceGateway->paceInput($user, $today),
            $this->performanceGateway->vo2maxInput($user, $today),
            $this->performanceGateway->consistencyPercent($user, $today),
        );

        $consistency = $this->consistencyService->forPeriod($user, $today->copy()->subDays(29), $today, type: self::TYPE);

        $latestVo2max = $this->metricSeries->latest($user, 'vo2max', 30);

        $longestRuns = $user->workouts()
            ->where('type', self::TYPE)
            ->orderByDesc('distance_meters')
            ->take(3)
            ->get();

        $personalRecords = $user->personalRecords()->orderBy('type')->get();

        return view('running.index', compact(
            'weekTotals', 'performance', 'consistency', 'latestVo2max', 'longestRuns', 'personalRecords'
        ));
    }

    public function distance(Request $request): View
    {
        $user = $request->user();
        $today = Carbon::today();

        $series = [];
        foreach (['distance', 'duration'] as $metric) {
            $series[$metric] = $this->series->meta($metric) + ['points' => $this->series->seriesFor($user, $metric, 365)];
        }

        $totals = $this->series->totalsForRange($user, $today->copy()->subDays(89), $today);

        return view('running.distance', compact('series', 'totals'));
    }

    public function pace(Request $request): View
    {
        $user = $request->user();

        $series = [];
        foreach (['pace', 'avg_hr'] as $metric) {
            $series[$metric] = $this->series->meta($metric) + ['points' => $this->series->seriesFor($user, $metric, 365)];
        }
        $series['vo2max'] = $this->metricSeries->meta('vo2max') + ['points' => $this->metricSeries->seriesFor($user, 'vo2max', 365)];

        $recentRuns = $user->workouts()
            ->where('type', self::TYPE)
            ->whereNotNull('average_pace_seconds_per_km')
            ->latest('start_date')
            ->take(20)
            ->get();

        return view('running.pace', compact('series', 'recentRuns'));
    }

    public function records(Request $request): View
    {
        $user = $request->user();

        $personalRecords = $user->personalRecords()->orderBy('type')->get();
        $longestRuns = $user->workouts()
            ->where('type', self::TYPE)
            ->orderByDesc('distance_meters')
            ->take(10)
            ->get();

        return view('running.records', compact('personalRecords', 'longestRuns'));
    }
}
