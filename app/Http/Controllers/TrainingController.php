<?php

namespace App\Http\Controllers;

use App\Models\TrainingPlan;
use App\Services\Health\PersonalBaselineService;
use App\Services\Training\ActivityDistributionService;
use App\Services\Training\HeartRateZoneService;
use App\Services\Training\TrainingCalendarService;
use App\Services\Training\TrainingConsistencyService;
use App\Services\Training\TrainingStatusEngine;
use App\Services\Training\TrainingVolumeSeriesService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class TrainingController extends Controller
{
    private const LOAD_HISTORY_DAYS = 90;

    private const VOLUME_METRICS = ['distance', 'duration', 'elevation_gain', 'activity_count'];

    public function __construct(
        private TrainingVolumeSeriesService $volumeSeries,
        private TrainingStatusEngine $statusEngine,
        private HeartRateZoneService $hrZones,
        private TrainingConsistencyService $consistencyService,
        private ActivityDistributionService $distributionService,
        private TrainingCalendarService $calendarService,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $today = Carbon::today();

        $plans = $user->trainingPlans()->latest('start_date')->get();
        $recentWorkouts = $user->workouts()->latest('start_date')->take(15)->get();
        $personalRecords = $user->personalRecords()->orderBy('type')->get();

        $weekTotals = $this->volumeSeries->totalsForRange($user, $today->copy()->subDays(6), $today);
        $status = $this->statusEngine->calculateAndStoreForDate($user, $today);
        $consistency = $this->consistencyService->forPeriod($user, $today->copy()->subDays(29), $today);
        $topTypes = array_slice($this->distributionService->byType($user, $today->copy()->subDays(29), $today), 0, 3);

        return view('training.index', compact(
            'plans', 'recentWorkouts', 'personalRecords',
            'weekTotals', 'status', 'consistency', 'topTypes'
        ));
    }

    public function volume(Request $request): View
    {
        $user = $request->user();
        $today = Carbon::today();

        $series = [];
        foreach (self::VOLUME_METRICS as $metric) {
            $series[$metric] = $this->volumeSeries->meta($metric) + [
                'points' => $this->volumeSeries->seriesFor($user, $metric, 365),
            ];
        }

        $totals = $this->volumeSeries->totalsForRange($user, $today->copy()->subDays(89), $today);

        return view('training.volume', compact('series', 'totals'));
    }

    public function load(Request $request): View
    {
        $user = $request->user();
        $today = Carbon::today();

        $status = $this->statusEngine->calculateAndStoreForDate($user, $today);

        $since = $today->copy()->subDays(self::LOAD_HISTORY_DAYS - 1);
        $history = $user->trainingLoads()
            ->where('date', '>=', $since)
            ->orderBy('date')
            ->get();

        $trendSeries = [
            'acute_load' => [
                'label' => 'Acute Load (7D avg)', 'unit' => '', 'color' => '#eb6834', 'decimals' => 1,
                'points' => $history->map(fn ($r) => ['date' => $r->date->toDateString(), 'value' => (float) $r->acute_load])->values()->all(),
            ],
            'acute_chronic_ratio' => [
                'label' => 'Acute:Chronic Ratio', 'unit' => '', 'color' => '#4a3aa7', 'decimals' => 2,
                'points' => $history->map(fn ($r) => ['date' => $r->date->toDateString(), 'value' => (float) ($r->acute_chronic_ratio ?? 0)])->values()->all(),
            ],
        ];

        $recentTrainingEffect = $user->workouts()
            ->whereNotNull('training_effect_label')
            ->latest('start_date')
            ->take(5)
            ->get();

        return view('training.load', [
            'status' => $status,
            'trendSeries' => $trendSeries,
            'recentTrainingEffect' => $recentTrainingEffect,
            'disclaimer' => PersonalBaselineService::DISCLAIMER,
        ]);
    }

    public function calendar(Request $request): View
    {
        $user = $request->user();
        $month = $this->parseMonth($request->query('month'));

        $calendar = $this->calendarService->forMonth($user, $month);
        $consistency = $this->consistencyService->forPeriod($user, $month->copy()->startOfMonth(), $month->copy()->endOfMonth());

        return view('training.calendar', compact('calendar', 'consistency'));
    }

    public function distribution(Request $request): View
    {
        $user = $request->user();
        $days = max(1, (int) $request->query('days', 30));
        $today = Carbon::today();
        $from = $today->copy()->subDays($days - 1);

        $types = $this->distributionService->byType($user, $from, $today);
        $hrZoneDistribution = $this->hrZones->distributionForPeriod($user, $from, $today);

        return view('training.distribution', compact('types', 'hrZoneDistribution', 'days'));
    }

    public function plan(Request $request, TrainingPlan $plan): View
    {
        $this->ensureOwnsPlan($request, $plan);

        $plan->load(['weeks.days.plannedWorkouts']);

        return view('training.plan', compact('plan'));
    }

    public function destroyPlan(Request $request, TrainingPlan $plan): \Illuminate\Http\RedirectResponse
    {
        $this->ensureOwnsPlan($request, $plan);

        $plan->delete();

        return redirect()->route('training')->with('status', 'Training plan dihapus.');
    }

    private function ensureOwnsPlan(Request $request, TrainingPlan $plan): void
    {
        abort_if($plan->user_id !== $request->user()->id, 403);
    }

    private function parseMonth(?string $month): Carbon
    {
        if ($month) {
            try {
                return Carbon::createFromFormat('Y-m-d', $month.'-01')->startOfMonth();
            } catch (\Exception) {
                // fall through to current month
            }
        }

        return Carbon::today()->startOfMonth();
    }
}
