<?php

namespace App\Services\Ai;

use App\Models\User;
use App\Services\Dashboard\InsightEngine;
use App\Services\Dashboard\TodaySnapshotService;
use App\Services\Health\MetricSeriesService;
use App\Services\Health\PersonalBaselineService;
use App\Services\Recovery\RecoveryEngine;
use App\Services\Running\RunningPerformanceCalculator;
use App\Services\Running\RunningPerformanceGateway;
use App\Services\Training\ActivityDistributionService;
use App\Services\Training\TrainingConsistencyService;
use App\Services\Training\TrainingStatusEngine;
use App\Services\Training\TrainingVolumeSeriesService;
use Illuminate\Support\Carbon;

/**
 * Assembles a compact, structured summary of a user's stored data for the AI
 * coach — never raw DB rows. Reuses the same services the dashboard/training/
 * recovery pages already call, so "structured" here means the same shaped
 * arrays those pages render from, just trimmed to a coaching-relevant window
 * (weeks, not a full year) to keep the system prompt small.
 */
class AiContextBuilder
{
    private const HEALTH_METRICS = ['resting_hr', 'hrv', 'stress', 'sleep', 'body_battery_net', 'spo2'];

    private const RECOVERY_HISTORY_DAYS = 14;

    public function __construct(
        private TodaySnapshotService $todaySnapshot,
        private InsightEngine $insights,
        private RecoveryEngine $recoveryEngine,
        private TrainingStatusEngine $trainingStatus,
        private TrainingVolumeSeriesService $volumeSeries,
        private TrainingConsistencyService $consistencyService,
        private ActivityDistributionService $distributionService,
        private RunningPerformanceGateway $runningGateway,
        private RunningPerformanceCalculator $runningCalculator,
        private MetricSeriesService $metricSeries,
        private PersonalBaselineService $baseline,
    ) {}

    /** @return array<string, mixed> */
    public function buildFor(User $user): array
    {
        $today = Carbon::today();
        $recoverySince = $today->copy()->subDays(self::RECOVERY_HISTORY_DAYS - 1);

        $recovery = $this->recoveryEngine->calculateAndStoreForDate($user, $today);
        $trainingStatus = $this->trainingStatus->calculateAndStoreForDate($user, $today);

        return [
            'user_name' => $user->name,
            'garmin_connected' => $user->garminConnection()->exists(),
            'today' => $this->todaySnapshot->forUser($user),
            'recent_insights' => $this->insights->generate($user),
            'recovery' => [
                'score' => $recovery['recovery']['model']->score,
                'score_breakdown' => $recovery['recovery']['breakdown'],
                'readiness_score' => $recovery['readiness']['model']->score,
                'readiness_breakdown' => $recovery['readiness']['breakdown'],
                'history_14d' => $user->recoveryScores()
                    ->where('date', '>=', $recoverySince)
                    ->orderBy('date')
                    ->get(['date', 'score'])
                    ->map(fn ($r) => ['date' => $r->date->toDateString(), 'score' => $r->score])
                    ->all(),
            ],
            'training_status' => [
                'acute_load' => $trainingStatus->acute_load,
                'chronic_load' => $trainingStatus->chronic_load,
                'acute_chronic_ratio' => $trainingStatus->acute_chronic_ratio,
                'monotony' => $trainingStatus->monotony,
                'risk_level' => $trainingStatus->risk_level,
                'weekly_distance_meters' => $trainingStatus->weekly_distance_meters,
                'weekly_duration_minutes' => $trainingStatus->weekly_duration_minutes,
                'training_frequency_per_week' => $trainingStatus->training_frequency,
            ],
            'week_totals' => $this->volumeSeries->totalsForRange($user, $today->copy()->subDays(6), $today),
            'consistency_30d' => $this->consistencyService->forPeriod($user, $today->copy()->subDays(29), $today),
            'top_activity_types_30d' => $this->distributionService->byType($user, $today->copy()->subDays(29), $today),
            'running_performance' => $this->runningCalculator->calculate(
                $this->runningGateway->runsInWindow($user, $today),
                $this->runningGateway->paceInput($user, $today),
                $this->runningGateway->vo2maxInput($user, $today),
                $this->runningGateway->consistencyPercent($user, $today),
            ),
            'health_vitals' => collect(self::HEALTH_METRICS)->mapWithKeys(function (string $metric) use ($user) {
                $latest = $this->metricSeries->latest($user, $metric);

                return [$metric => [
                    'label' => $this->metricSeries->meta($metric)['label'] ?? $metric,
                    'latest_value' => $latest['value'] ?? null,
                    'personal_baseline' => $this->baseline->compute($user, $metric),
                ]];
            })->all(),
            'personal_records' => $user->personalRecords()->orderBy('type')->get(['type', 'value', 'achieved_date'])
                ->map(fn ($pr) => ['type' => $pr->type, 'value' => $pr->formattedValue(), 'achieved_date' => $pr->achieved_date->toDateString()])
                ->all(),
            'recent_workouts' => $user->workouts()->latest('start_date')->take(8)
                ->get(['type', 'start_date', 'distance_meters', 'average_heart_rate', 'training_load'])
                ->map(fn ($w) => [
                    'type' => $w->type,
                    'date' => $w->start_date->toDateString(),
                    'distance_meters' => $w->distance_meters,
                    'average_heart_rate' => $w->average_heart_rate,
                    'training_load' => $w->training_load,
                ])->all(),
            'disclaimer' => PersonalBaselineService::DISCLAIMER,
        ];
    }
}
