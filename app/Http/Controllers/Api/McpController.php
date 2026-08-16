<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Dashboard\InsightEngine;
use App\Services\Dashboard\TodaySnapshotService;
use App\Services\Health\MetricSeriesService;
use App\Services\Health\PersonalBaselineService;
use App\Services\Recovery\RecoveryEngine;
use App\Services\Running\RunningPerformanceCalculator;
use App\Services\Running\RunningPerformanceGateway;
use App\Services\Running\RunningSeriesService;
use App\Services\Trail\TrailMovingTimeCalculator;
use App\Services\Trail\TrailRouteGroupingService;
use App\Services\Trail\TrailSeriesService;
use App\Services\Training\ActivityDistributionService;
use App\Services\Training\TrainingConsistencyService;
use App\Services\Training\TrainingStatusEngine;
use App\Services\Training\TrainingVolumeSeriesService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Per-user JSON views onto the same services the web controllers use, plus
 * write-back endpoints — built for external tool-callers (the MCP server)
 * that need structured data to reason about a plan, and a way to hand a
 * plan back to RAGA once they've built one, not just read from it.
 */
class McpController extends Controller
{
    private const RECOVERY_HISTORY_DAYS = 30;

    private const LOAD_HISTORY_DAYS = 90;

    private const HEALTH_OVERVIEW_METRICS = [
        'resting_hr', 'avg_hr', 'max_hr', 'hrv', 'stress',
        'body_battery_net', 'respiration', 'spo2', 'steps', 'calories', 'recovery_time',
        'garmin_readiness',
    ];

    public function overview(Request $request, TodaySnapshotService $today, InsightEngine $insights): array
    {
        $user = $request->user();

        return [
            'user' => ['name' => $user->name, 'email' => $user->email],
            'garmin_connected' => $user->garminConnection()->exists(),
            'today' => $today->forUser($user),
            'insights' => $insights->generate($user),
        ];
    }

    public function training(
        Request $request,
        TrainingStatusEngine $statusEngine,
        TrainingVolumeSeriesService $volumeSeries,
        TrainingConsistencyService $consistencyService,
        ActivityDistributionService $distributionService,
    ): array {
        $user = $request->user();
        $today = Carbon::today();
        $since = $today->copy()->subDays(self::LOAD_HISTORY_DAYS - 1);

        $status = $statusEngine->calculateAndStoreForDate($user, $today);

        $loadHistory = $user->trainingLoads()
            ->where('date', '>=', $since)
            ->orderBy('date')
            ->get(['date', 'acute_load', 'chronic_load', 'acute_chronic_ratio', 'monotony', 'risk_level']);

        return [
            'status' => $status,
            'load_history' => $loadHistory,
            'week_totals' => $volumeSeries->totalsForRange($user, $today->copy()->subDays(6), $today),
            'consistency_30d' => $consistencyService->forPeriod($user, $today->copy()->subDays(29), $today),
            'top_activity_types_30d' => $distributionService->byType($user, $today->copy()->subDays(29), $today),
            'active_plans' => $user->trainingPlans()->latest('start_date')->get(),
            'recent_workouts' => $user->workouts()->latest('start_date')->take(15)->get(),
            'personal_records' => $user->personalRecords()->orderBy('type')->get(),
        ];
    }

    public function recovery(Request $request, RecoveryEngine $engine): array
    {
        $user = $request->user();
        $today = Carbon::today();
        $since = $today->copy()->subDays(self::RECOVERY_HISTORY_DAYS - 1);

        $result = $engine->calculateAndStoreForDate($user, $today);

        return [
            'recovery' => $result['recovery'],
            'readiness' => $result['readiness'],
            'recovery_history' => $user->recoveryScores()->where('date', '>=', $since)->orderBy('date')
                ->get(['date', 'score']),
            'readiness_history' => $user->readinessScores()->where('date', '>=', $since)->orderBy('date')
                ->get(['date', 'score']),
            'disclaimer' => PersonalBaselineService::DISCLAIMER,
        ];
    }

    public function health(Request $request, MetricSeriesService $metricSeries, PersonalBaselineService $baseline): array
    {
        $user = $request->user();

        $overview = collect(self::HEALTH_OVERVIEW_METRICS)->mapWithKeys(function (string $metric) use ($user, $metricSeries, $baseline) {
            $latest = $metricSeries->latest($user, $metric);

            return [$metric => [
                'label' => $metricSeries->meta($metric)['label'] ?? $metric,
                'value' => $latest['value'] ?? null,
                'baseline' => $baseline->compute($user, $metric),
            ]];
        });

        return [
            'metrics' => $overview,
            'disclaimer' => PersonalBaselineService::DISCLAIMER,
        ];
    }

    public function running(
        Request $request,
        RunningSeriesService $series,
        RunningPerformanceGateway $performanceGateway,
        RunningPerformanceCalculator $performanceCalculator,
        TrainingConsistencyService $consistencyService,
        MetricSeriesService $metricSeries,
    ): array {
        $user = $request->user();
        $today = Carbon::today();

        $performance = $performanceCalculator->calculate(
            $performanceGateway->runsInWindow($user, $today),
            $performanceGateway->paceInput($user, $today),
            $performanceGateway->vo2maxInput($user, $today),
            $performanceGateway->consistencyPercent($user, $today),
        );

        return [
            'week_totals' => $series->totalsForRange($user, $today->copy()->subDays(6), $today),
            'performance' => $performance,
            'consistency_30d' => $consistencyService->forPeriod($user, $today->copy()->subDays(29), $today, type: 'running'),
            'latest_vo2max' => $metricSeries->latest($user, 'vo2max', 30),
            'longest_runs' => $user->workouts()->where('type', 'running')->orderByDesc('distance_meters')->take(5)->get(),
            'personal_records' => $user->personalRecords()->orderBy('type')->get(),
        ];
    }

    public function trail(
        Request $request,
        TrailSeriesService $series,
        TrailMovingTimeCalculator $movingTime,
        TrailRouteGroupingService $routeGrouping,
    ): array {
        $user = $request->user();
        $today = Carbon::today();
        $from = $today->copy()->subDays(89);

        $recentTrailRuns = $user->workouts()
            ->where('type', 'trail_running')
            ->whereBetween('start_date', [$from, $today->copy()->endOfDay()])
            ->orderByDesc('start_date')
            ->get();

        return [
            'totals_90d' => $series->totalsForRange($user, $from, $today),
            'moving_seconds_90d' => $recentTrailRuns->sum(fn ($workout) => $movingTime->movingSecondsForWorkout($workout)),
            'repeated_routes' => count($routeGrouping->repeatedRoutes($user)),
            'recent_runs' => $recentTrailRuns->take(10)->values(),
        ];
    }

    public function saveTrainingPlan(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'start_date' => ['required', 'date'],
            'target_date' => ['required', 'date', 'after_or_equal:start_date'],
            'weeks' => ['required', 'array', 'min:1'],
            'weeks.*.week_number' => ['required', 'integer', 'min:1'],
            'weeks.*.start_date' => ['required', 'date'],
            'weeks.*.end_date' => ['required', 'date', 'after_or_equal:weeks.*.start_date'],
            'weeks.*.days' => ['required', 'array', 'min:1'],
            'weeks.*.days.*.date' => ['required', 'date'],
            'weeks.*.days.*.workouts' => ['sometimes', 'array'],
            'weeks.*.days.*.workouts.*.type' => ['required', 'string', 'max:255'],
            'weeks.*.days.*.workouts.*.duration_minutes' => ['nullable', 'numeric', 'min:0'],
            'weeks.*.days.*.workouts.*.distance_meters' => ['nullable', 'numeric', 'min:0'],
            'weeks.*.days.*.workouts.*.target_pace_seconds_per_km' => ['nullable', 'numeric', 'min:0'],
            'weeks.*.days.*.workouts.*.target_heart_rate_zone' => ['nullable', 'integer', 'min:1', 'max:5'],
            'weeks.*.days.*.workouts.*.intensity' => ['nullable', 'string', 'max:255'],
            'weeks.*.days.*.workouts.*.warm_up' => ['nullable', 'string'],
            'weeks.*.days.*.workouts.*.main_set' => ['nullable', 'string'],
            'weeks.*.days.*.workouts.*.cool_down' => ['nullable', 'string'],
            'weeks.*.days.*.workouts.*.notes' => ['nullable', 'string'],
        ]);

        $user = $request->user();

        $plan = DB::transaction(function () use ($user, $data) {
            $plan = $user->trainingPlans()->create([
                'name' => $data['name'],
                'start_date' => $data['start_date'],
                'target_date' => $data['target_date'],
                'status' => 'active',
            ]);

            foreach ($data['weeks'] as $weekData) {
                $week = $plan->weeks()->create([
                    'week_number' => $weekData['week_number'],
                    'start_date' => $weekData['start_date'],
                    'end_date' => $weekData['end_date'],
                ]);

                foreach ($weekData['days'] as $dayData) {
                    $day = $week->days()->create(['date' => $dayData['date']]);

                    foreach ($dayData['workouts'] ?? [] as $workoutData) {
                        $day->plannedWorkouts()->create([
                            'type' => $workoutData['type'],
                            'duration_minutes' => $workoutData['duration_minutes'] ?? null,
                            'distance_meters' => $workoutData['distance_meters'] ?? null,
                            'target_pace_seconds_per_km' => $workoutData['target_pace_seconds_per_km'] ?? null,
                            'target_heart_rate_zone' => $workoutData['target_heart_rate_zone'] ?? null,
                            'intensity' => $workoutData['intensity'] ?? null,
                            'warm_up' => $workoutData['warm_up'] ?? null,
                            'main_set' => $workoutData['main_set'] ?? null,
                            'cool_down' => $workoutData['cool_down'] ?? null,
                            'notes' => $workoutData['notes'] ?? null,
                            'status' => 'planned',
                        ]);
                    }
                }
            }

            return $plan;
        });

        return [
            'training_plan_id' => $plan->id,
            'weeks_created' => count($data['weeks']),
            'view_url' => url('/training/calendar'),
        ];
    }

    public function saveRecommendation(Request $request): array
    {
        $data = $request->validate([
            'date' => ['required', 'date'],
            'category' => ['required', 'string', 'max:255'],
            'title' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string'],
            'priority' => ['sometimes', 'integer', 'min:0', 'max:10'],
        ]);

        $recommendation = $request->user()->recommendations()->create([
            'date' => $data['date'],
            'category' => $data['category'],
            'title' => $data['title'],
            'message' => $data['message'],
            'priority' => $data['priority'] ?? 0,
            'source' => 'ai',
        ]);

        return [
            'recommendation_id' => $recommendation->id,
            'view_url' => url('/dashboard'),
        ];
    }
}
