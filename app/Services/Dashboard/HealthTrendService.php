<?php

namespace App\Services\Dashboard;

use App\Models\User;
use Illuminate\Support\Carbon;

class HealthTrendService
{
    private const WINDOW_DAYS = 90;

    /** Fixed per-metric identity — validated against the dataviz skill's CVD-safe categorical order. */
    private const METRICS = [
        'resting_hr' => ['label' => 'Resting HR', 'unit' => 'bpm', 'color' => '#2a78d6', 'decimals' => 0],
        'training_load' => ['label' => 'Training Load (est.)', 'unit' => 'kcal', 'color' => '#eb6834', 'decimals' => 0],
        'hrv' => ['label' => 'HRV', 'unit' => 'ms', 'color' => '#1baf7a', 'decimals' => 0],
        'body_battery' => ['label' => 'Body Battery (net)', 'unit' => 'pts', 'color' => '#eda100', 'decimals' => 0],
        'sleep' => ['label' => 'Sleep', 'unit' => 'hrs', 'color' => '#e87ba4', 'decimals' => 1],
    ];

    public function __construct(private TrainingLoadCalculator $trainingLoad) {}

    /**
     * @return array<string, array{label: string, unit: string, color: string, points: list<array{date: string, value: float}>}>
     */
    public function allSeries(User $user): array
    {
        $since = Carbon::today()->subDays(self::WINDOW_DAYS - 1);

        $series = [];
        foreach (self::METRICS as $key => $meta) {
            $series[$key] = $meta + ['points' => $this->pointsFor($user, $key, $since)];
        }

        return $series;
    }

    /** @return list<array{date: string, value: float}> */
    private function pointsFor(User $user, string $metric, Carbon $since): array
    {
        return match ($metric) {
            'resting_hr' => $this->vitalSeries($user, 'resting_heart_rate', $since),
            'hrv' => $this->vitalSeries($user, 'hrv_overnight_avg', $since),
            'sleep' => $this->sleepSeries($user, $since),
            'body_battery' => $this->bodyBatterySeries($user, $since),
            'training_load' => $this->trainingLoadSeries($user, $since),
            default => [],
        };
    }

    /** @return list<array{date: string, value: float}> */
    private function vitalSeries(User $user, string $type, Carbon $since): array
    {
        return $user->vitalMeasurements()
            ->where('type', $type)
            ->where('date', '>=', $since)
            ->orderBy('date')
            ->get()
            ->map(fn ($v) => ['date' => $v->date->toDateString(), 'value' => (float) $v->value])
            ->values()
            ->all();
    }

    /** @return list<array{date: string, value: float}> */
    private function sleepSeries(User $user, Carbon $since): array
    {
        return $user->sleepSessions()
            ->where('bedtime', '>=', $since)
            ->orderBy('bedtime')
            ->get()
            ->map(fn ($s) => [
                'date' => $s->bedtime->toDateString(),
                'value' => round($s->totalDurationMinutes() / 60, 2),
            ])
            ->values()
            ->all();
    }

    /** @return list<array{date: string, value: float}> */
    private function bodyBatterySeries(User $user, Carbon $since): array
    {
        $charged = $user->vitalMeasurements()
            ->where('type', 'body_battery_charged')
            ->where('date', '>=', $since)
            ->get()
            ->keyBy(fn ($v) => $v->date->toDateString());

        $drained = $user->vitalMeasurements()
            ->where('type', 'body_battery_drained')
            ->where('date', '>=', $since)
            ->get()
            ->keyBy(fn ($v) => $v->date->toDateString());

        $dates = $charged->keys()->merge($drained->keys())->unique()->sort()->values();

        return $dates->map(fn ($date) => [
            'date' => $date,
            'value' => (float) (($charged[$date]->value ?? 0) - ($drained[$date]->value ?? 0)),
        ])->all();
    }

    /** @return list<array{date: string, value: float}> */
    private function trainingLoadSeries(User $user, Carbon $since): array
    {
        return $user->activitySummaries()
            ->where('date', '>=', $since)
            ->orderBy('date')
            ->get()
            ->map(fn ($a) => [
                'date' => $a->date->toDateString(),
                'value' => $this->trainingLoad->forActivitySummary($a),
            ])
            ->values()
            ->all();
    }
}
