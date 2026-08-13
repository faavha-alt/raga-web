<?php

namespace App\Services\Training;

use App\Models\User;
use App\Models\WorkoutSample;
use Illuminate\Support\Carbon;

/**
 * Estimates HR zone time distribution from raw per-sample heart rate data.
 * No max-HR profile field exists anywhere in this app (no age/DOB either),
 * so max HR is estimated transparently from the user's own highest ever
 * recorded value — never an age-based formula guess. Every UI display of
 * this figure must be labeled as an estimate from the user's own data.
 */
class HeartRateZoneService
{
    /** Standard 5-zone %-of-max-HR bands. */
    private const ZONES = [
        1 => ['label' => 'Z1 Recovery', 'max_pct' => 0.60],
        2 => ['label' => 'Z2 Easy', 'max_pct' => 0.70],
        3 => ['label' => 'Z3 Moderate', 'max_pct' => 0.80],
        4 => ['label' => 'Z4 Hard', 'max_pct' => 0.90],
        5 => ['label' => 'Z5 Max', 'max_pct' => PHP_FLOAT_MAX],
    ];

    /** A gap larger than this (e.g. a paused/resumed activity) isn't attributed to any zone. */
    private const MAX_SAMPLE_GAP_SECONDS = 60;

    public function estimatedMaxHeartRate(User $user): ?int
    {
        $fromWorkouts = (int) ($user->workouts()->max('max_heart_rate') ?? 0);

        $fromSamples = (int) (WorkoutSample::query()
            ->whereIn('workout_id', $user->workouts()->select('id'))
            ->max('heart_rate') ?? 0);

        $max = max($fromWorkouts, $fromSamples);

        return $max > 0 ? $max : null;
    }

    /**
     * @return array{
     *     available: bool,
     *     max_hr: ?int,
     *     zones: list<array{zone:int,label:string,seconds:int,percent:float}>,
     *     workouts_with_samples: int,
     *     workouts_total: int,
     * }
     */
    public function distributionForPeriod(User $user, Carbon $from, Carbon $to): array
    {
        $maxHr = $this->estimatedMaxHeartRate($user);

        $workouts = $user->workouts()
            ->whereBetween('start_date', [$from, $to->copy()->endOfDay()])
            ->get(['id']);

        if ($maxHr === null || $workouts->isEmpty()) {
            return [
                'available' => false,
                'max_hr' => $maxHr,
                'zones' => $this->emptyZones(),
                'workouts_with_samples' => 0,
                'workouts_total' => $workouts->count(),
            ];
        }

        // Batched fetch (not per-workout in a loop) to avoid N+1.
        $samplesByWorkout = WorkoutSample::query()
            ->whereIn('workout_id', $workouts->pluck('id'))
            ->whereNotNull('heart_rate')
            ->orderBy('workout_id')
            ->orderBy('timestamp')
            ->get(['workout_id', 'timestamp', 'heart_rate'])
            ->groupBy('workout_id');

        $secondsByZone = array_fill_keys(array_keys(self::ZONES), 0);
        $workoutsWithSamples = 0;

        foreach ($samplesByWorkout as $samples) {
            if ($samples->count() < 2) {
                continue;
            }

            $workoutsWithSamples++;

            $plainSamples = $samples->map(fn ($sample) => [
                'timestamp' => $sample->timestamp,
                'heart_rate' => (float) $sample->heart_rate,
            ])->values()->all();

            foreach ($this->bucketSamples($plainSamples, $maxHr) as $zone => $seconds) {
                $secondsByZone[$zone] += $seconds;
            }
        }

        $totalSeconds = array_sum($secondsByZone);

        return [
            'available' => true,
            'max_hr' => $maxHr,
            'zones' => $this->zonesFromSeconds($secondsByZone, $totalSeconds),
            'workouts_with_samples' => $workoutsWithSamples,
            'workouts_total' => $workouts->count(),
        ];
    }

    /**
     * Pure zone-bucketing math, DB-free — takes a chronologically-ordered
     * list of {timestamp: Carbon, heart_rate: float} and returns seconds
     * spent in each zone. Zone duration is weighted by time between
     * consecutive samples, not sample count, so irregular sampling
     * intervals don't skew the result.
     *
     * @param  list<array{timestamp: Carbon, heart_rate: float}>  $samples
     * @return array<int, int> zone number => seconds
     */
    public function bucketSamples(array $samples, int $maxHr): array
    {
        $secondsByZone = array_fill_keys(array_keys(self::ZONES), 0);

        for ($i = 0; $i < count($samples) - 1; $i++) {
            $current = $samples[$i];
            $next = $samples[$i + 1];

            $gapSeconds = (int) round(abs($current['timestamp']->diffInSeconds($next['timestamp'])));

            if ($gapSeconds <= 0 || $gapSeconds > self::MAX_SAMPLE_GAP_SECONDS) {
                continue;
            }

            $zone = $this->zoneFor($current['heart_rate'], $maxHr);
            $secondsByZone[$zone] += $gapSeconds;
        }

        return $secondsByZone;
    }

    private function zoneFor(float $heartRate, int $maxHr): int
    {
        $pct = $heartRate / $maxHr;

        foreach (self::ZONES as $number => $zone) {
            if ($pct <= $zone['max_pct']) {
                return $number;
            }
        }

        return 5;
    }

    /** @return list<array{zone:int,label:string,seconds:int,percent:float}> */
    private function emptyZones(): array
    {
        return $this->zonesFromSeconds(array_fill_keys(array_keys(self::ZONES), 0), 0);
    }

    /** @param array<int,int> $secondsByZone */
    private function zonesFromSeconds(array $secondsByZone, int $totalSeconds): array
    {
        return collect(self::ZONES)->map(fn ($zone, $number) => [
            'zone' => $number,
            'label' => $zone['label'],
            'seconds' => $secondsByZone[$number],
            'percent' => $totalSeconds > 0 ? round($secondsByZone[$number] / $totalSeconds * 100, 1) : 0.0,
        ])->values()->all();
    }
}
