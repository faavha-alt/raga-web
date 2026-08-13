<?php

namespace App\Services\Health;

use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Single source of truth for "N days of daily values for metric X" across
 * the Health module. Average/Max HR are computed here from raw
 * heart_rate_samples (real synced data, just aggregated) since Garmin sync
 * never stores a daily avg/max HR vital directly.
 */
class MetricSeriesService
{
    private const METRICS = [
        'resting_hr' => ['label' => 'Resting HR', 'unit' => 'bpm', 'color' => '#2a78d6', 'decimals' => 0],
        'avg_hr' => ['label' => 'Average HR', 'unit' => 'bpm', 'color' => '#1baf7a', 'decimals' => 0],
        'max_hr' => ['label' => 'Max HR', 'unit' => 'bpm', 'color' => '#e34948', 'decimals' => 0],
        'hrv' => ['label' => 'HRV', 'unit' => 'ms', 'color' => '#4a3aa7', 'decimals' => 0],
        'stress' => ['label' => 'Stress', 'unit' => 'level', 'color' => '#eda100', 'decimals' => 0],
        'body_battery_charged' => ['label' => 'Body Battery Charged', 'unit' => 'pts', 'color' => '#1baf7a', 'decimals' => 0],
        'body_battery_drained' => ['label' => 'Body Battery Drained', 'unit' => 'pts', 'color' => '#e34948', 'decimals' => 0],
        'body_battery_net' => ['label' => 'Body Battery Net', 'unit' => 'pts', 'color' => '#4a3aa7', 'decimals' => 0],
        'respiration' => ['label' => 'Respiration', 'unit' => 'breaths/min', 'color' => '#2a78d6', 'decimals' => 0],
        'spo2' => ['label' => 'SpO2', 'unit' => '%', 'color' => '#1baf7a', 'decimals' => 0],
        'steps' => ['label' => 'Steps', 'unit' => 'steps', 'color' => '#eda100', 'decimals' => 0],
        'calories' => ['label' => 'Calories', 'unit' => 'kcal', 'color' => '#4a3aa7', 'decimals' => 0],
        'recovery_time' => ['label' => 'Recovery Time', 'unit' => 'min', 'color' => '#e34948', 'decimals' => 0],
        'sleep' => ['label' => 'Sleep', 'unit' => 'hrs', 'color' => '#e87ba4', 'decimals' => 1],
        'training_load' => ['label' => 'Training Load', 'unit' => 'pts', 'color' => '#eb6834', 'decimals' => 0],
        'garmin_readiness' => ['label' => 'Garmin Readiness', 'unit' => '', 'color' => '#4a3aa7', 'decimals' => 0],
    ];

    public function meta(string $metric): array
    {
        return self::METRICS[$metric] ?? ['label' => $metric, 'unit' => '', 'color' => '#2a78d6', 'decimals' => 0];
    }

    /** @return list<array{date: string, value: float}> */
    public function seriesFor(User $user, string $metric, int $days): array
    {
        $since = Carbon::today()->subDays($days - 1);

        return match ($metric) {
            'resting_hr' => $this->vitalSeries($user, 'resting_heart_rate', $since),
            'hrv' => $this->vitalSeries($user, 'hrv_overnight_avg', $since),
            'stress' => $this->vitalSeries($user, 'stress_avg', $since),
            'respiration' => $this->vitalSeries($user, 'respiration_rate', $since),
            'spo2' => $this->vitalSeries($user, 'spo2_avg', $since),
            'recovery_time' => $this->vitalSeries($user, 'recovery_time', $since),
            'garmin_readiness' => $this->vitalSeries($user, 'training_readiness', $since),
            'body_battery_charged' => $this->vitalSeries($user, 'body_battery_charged', $since),
            'body_battery_drained' => $this->vitalSeries($user, 'body_battery_drained', $since),
            'body_battery_net' => $this->bodyBatteryNetSeries($user, $since),
            'avg_hr' => $this->heartRateAggregateSeries($user, $since, 'AVG'),
            'max_hr' => $this->heartRateAggregateSeries($user, $since, 'MAX'),
            'steps' => $this->activitySummarySeries($user, $since, 'steps'),
            'calories' => $this->activitySummarySeries($user, $since, 'active_calories'),
            'sleep' => $this->sleepSeries($user, $since),
            'training_load' => $this->trainingLoadSeries($user, $since),
            default => [],
        };
    }

    /** Most recent point within the last $lookbackDays, or null if there isn't one. */
    public function latest(User $user, string $metric, int $lookbackDays = 7): ?array
    {
        $points = $this->seriesFor($user, $metric, $lookbackDays);

        return $points === [] ? null : end($points);
    }

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

    private function bodyBatteryNetSeries(User $user, Carbon $since): array
    {
        $charged = $user->vitalMeasurements()->where('type', 'body_battery_charged')->where('date', '>=', $since)
            ->get()->keyBy(fn ($v) => $v->date->toDateString());
        $drained = $user->vitalMeasurements()->where('type', 'body_battery_drained')->where('date', '>=', $since)
            ->get()->keyBy(fn ($v) => $v->date->toDateString());

        $dates = $charged->keys()->merge($drained->keys())->unique()->sort()->values();

        return $dates->map(fn ($date) => [
            'date' => $date,
            'value' => (float) (($charged[$date]->value ?? 0) - ($drained[$date]->value ?? 0)),
        ])->all();
    }

    /** DATE()/AVG()/MAX()/GROUP BY are standard SQL — safe on both MySQL (prod) and SQLite (tests). */
    private function heartRateAggregateSeries(User $user, Carbon $since, string $aggregate): array
    {
        $rows = $user->heartRateSamples()
            ->where('timestamp', '>=', $since)
            ->selectRaw("DATE(timestamp) as sample_date, {$aggregate}(bpm) as agg_value")
            ->groupBy('sample_date')
            ->orderBy('sample_date')
            ->get();

        return $rows->map(fn ($row) => ['date' => $row->sample_date, 'value' => (float) $row->agg_value])->all();
    }

    private function activitySummarySeries(User $user, Carbon $since, string $column): array
    {
        return $user->activitySummaries()
            ->where('date', '>=', $since)
            ->orderBy('date')
            ->get()
            ->map(fn ($a) => ['date' => $a->date->toDateString(), 'value' => (float) $a->{$column}])
            ->values()
            ->all();
    }

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

    /** SUM()/DATE()/GROUP BY are standard SQL — safe on both MySQL (prod) and SQLite (tests). */
    private function trainingLoadSeries(User $user, Carbon $since): array
    {
        $rows = $user->workouts()
            ->where('start_date', '>=', $since)
            ->whereNotNull('training_load')
            ->selectRaw('DATE(start_date) as workout_date, SUM(training_load) as total_load')
            ->groupBy('workout_date')
            ->orderBy('workout_date')
            ->get();

        return $rows->map(fn ($row) => ['date' => $row->workout_date, 'value' => (float) $row->total_load])->all();
    }
}
