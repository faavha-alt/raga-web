<?php

namespace App\Services\Dashboard;

use App\Models\User;
use Illuminate\Support\Carbon;

class TodaySnapshotService
{
    public function __construct(private TrainingLoadCalculator $trainingLoad) {}

    /**
     * @return array{
     *     readiness: ?float,
     *     sleep: array{minutes: ?float, score: ?int},
     *     hrv: ?float,
     *     resting_heart_rate: ?float,
     *     body_battery: array{charged: ?float, drained: ?float},
     *     stress: ?float,
     *     training_load: float,
     * }
     */
    public function forUser(User $user): array
    {
        $today = Carbon::today();

        // The computed Readiness Score (App\Services\Recovery\RecoveryEngine) — a
        // transparent factor breakdown, not Garmin's own opaque training_readiness
        // vital (that one's still visible, labeled "Garmin Readiness", on the
        // Health Overview page).
        $readinessScore = $user->readinessScores()->whereDate('date', $today)->first();
        $hrv = $this->latestVital($user, 'hrv_overnight_avg');
        $restingHr = $this->latestVital($user, 'resting_heart_rate');
        $stress = $this->latestVital($user, 'stress_avg');
        $bodyBatteryCharged = $this->latestVital($user, 'body_battery_charged');
        $bodyBatteryDrained = $this->latestVital($user, 'body_battery_drained');

        $sleep = $user->sleepSessions()->latest('bedtime')->first();

        $todayActivity = $user->activitySummaries()->whereDate('date', $today)->first();

        return [
            'readiness' => $readinessScore?->score,
            'sleep' => [
                'minutes' => $sleep?->totalDurationMinutes(),
                'score' => $sleep?->sleep_score,
            ],
            'hrv' => $hrv?->value,
            'resting_heart_rate' => $restingHr?->value,
            'body_battery' => [
                'charged' => $bodyBatteryCharged?->value,
                'drained' => $bodyBatteryDrained?->value,
            ],
            'stress' => $stress?->value,
            'training_load' => $this->trainingLoad->forActivitySummary($todayActivity),
        ];
    }

    private function latestVital(User $user, string $type)
    {
        return $user->vitalMeasurements()->where('type', $type)->latest('date')->first();
    }
}
