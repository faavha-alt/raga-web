<?php

namespace App\Console\Commands;

use App\Models\ActivitySummary;
use App\Models\BodyMeasurement;
use App\Models\HeartRateSample;
use App\Models\HrvSample;
use App\Models\PersonalRecord;
use App\Models\SleepSession;
use App\Models\User;
use App\Models\VitalMeasurement;
use App\Models\Workout;
use App\Models\WorkoutLap;
use App\Models\WorkoutSample;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ImportGarminData extends Command
{
    protected $signature = 'garmin:import {path : JSON file from garmin_sync.py, or - for stdin} {--user= : Target user ID (defaults to the first user)}';

    protected $description = 'Import health data pulled from Garmin Connect (via scripts/garmin_sync.py) into the RAGA database';

    private const SOURCE = 'garmin';

    // Garmin's personal-record "typeId" is an undocumented internal enum. These first few
    // are inferred with reasonably high confidence from Garmin Connect's known PR ordering
    // (1K/1mi/5K/10K/half/full-marathon/longest run) and cross-checked against real pace
    // and activity-name data. Anything else (e.g. cycling PRs) is intentionally left as a
    // raw "garmin_pr_type_N" label rather than guessed, to avoid mislabeling data that may
    // later feed AI training decisions.
    private const PR_TYPE_LABELS = [
        1 => 'fastest_1k',
        2 => 'fastest_1_mile',
        3 => 'fastest_5k',
        4 => 'fastest_10k',
        5 => 'fastest_half_marathon',
        6 => 'fastest_marathon',
        7 => 'longest_run',
    ];

    public function handle(): int
    {
        $raw = $this->argument('path') === '-'
            ? stream_get_contents(STDIN)
            : file_get_contents($this->argument('path'));

        if ($raw === false || $raw === '') {
            $this->error('No input received.');

            return self::FAILURE;
        }

        $payload = json_decode($raw, true);

        if (! is_array($payload)) {
            $this->error('Input was not valid JSON.');

            return self::FAILURE;
        }

        $user = $this->option('user')
            ? User::findOrFail($this->option('user'))
            : User::oldest()->firstOrFail();

        foreach ($payload['daily'] ?? [] as $day) {
            $this->importDay($user, $day);
        }

        $this->importBodyComposition($user, $payload['body_composition'] ?? null);
        $this->importBodyBattery($user, $payload['body_battery'] ?? []);
        $this->importActivities($user, $payload['activities'] ?? []);
        $this->importPersonalRecords($user, $payload['personal_records'] ?? null);

        $this->info('Garmin import complete for '.$user->email);

        return self::SUCCESS;
    }

    private function importDay(User $user, array $day): void
    {
        $date = $day['date'] ?? null;

        if (! $date) {
            return;
        }

        $this->importStats($user, $date, $day['stats'] ?? null);
        $this->importSleep($user, $date, $day['sleep'] ?? null);
        $this->importHrv($user, $date, $day['hrv'] ?? null);
        $this->importHeartRate($user, $date, $day['heart_rate'] ?? null);
        $this->importStress($user, $date, $day['stress'] ?? null);
        $this->importSpo2($user, $date, $day['spo2'] ?? null);
        $this->importMaxMetrics($user, $date, $day['max_metrics'] ?? null);
        $this->importRespiration($user, $date, $day['respiration'] ?? null);
        $this->importTrainingReadiness($user, $date, $day['training_readiness'] ?? null);
    }

    private function importStats(User $user, string $date, ?array $stats): void
    {
        if (! $stats) {
            return;
        }

        ActivitySummary::updateOrCreate(
            ['user_id' => $user->id, 'date' => $date],
            [
                'steps' => $stats['totalSteps'] ?? 0,
                'distance_meters' => $stats['totalDistanceMeters'] ?? 0,
                'active_calories' => $stats['activeKilocalories'] ?? 0,
                'exercise_minutes' => ($stats['moderateIntensityMinutes'] ?? 0) + ($stats['vigorousIntensityMinutes'] ?? 0),
                'source' => self::SOURCE,
            ]
        );

        if (isset($stats['restingHeartRate'])) {
            $this->putVital($user, 'resting_heart_rate', (float) $stats['restingHeartRate'], 'bpm', $date);
        }
    }

    private function importSleep(User $user, string $date, ?array $sleep): void
    {
        $dto = $sleep['dailySleepDTO'] ?? null;

        if (! $dto || empty($dto['sleepStartTimestampGMT'])) {
            return;
        }

        SleepSession::where('user_id', $user->id)
            ->where('source', self::SOURCE)
            ->whereDate('bedtime', $date)
            ->delete();

        $overallScore = $dto['sleepScores']['overall']['value']
            ?? $sleep['sleepScores']['overall']['value']
            ?? null;

        SleepSession::create([
            'user_id' => $user->id,
            'bedtime' => Carbon::parse($dto['sleepStartTimestampGMT']),
            'wake_time' => Carbon::parse($dto['sleepEndTimestampGMT']),
            'rem_minutes' => ($dto['remSleepSeconds'] ?? 0) / 60,
            'deep_minutes' => ($dto['deepSleepSeconds'] ?? 0) / 60,
            'core_minutes' => ($dto['lightSleepSeconds'] ?? 0) / 60,
            'awake_minutes' => ($dto['awakeSleepSeconds'] ?? 0) / 60,
            'sleep_score' => $overallScore,
            'source' => self::SOURCE,
        ]);
    }

    private function importHrv(User $user, string $date, ?array $hrv): void
    {
        if (! $hrv) {
            return;
        }

        $summary = $hrv['hrvSummary'] ?? null;
        if ($summary && isset($summary['lastNightAvg'])) {
            $this->putVital($user, 'hrv_overnight_avg', (float) $summary['lastNightAvg'], 'ms', $date);
        }

        $readings = $hrv['hrvReadings'] ?? [];
        if (empty($readings)) {
            return;
        }

        HrvSample::where('user_id', $user->id)
            ->where('source', self::SOURCE)
            ->whereDate('timestamp', $date)
            ->delete();

        $rows = [];
        $now = now();
        foreach ($readings as $reading) {
            if (! isset($reading['readingTimeGMT'], $reading['hrvValue'])) {
                continue;
            }
            $rows[] = [
                'user_id' => $user->id,
                'timestamp' => Carbon::parse($reading['readingTimeGMT']),
                'sdnn_milliseconds' => $reading['hrvValue'],
                'source' => self::SOURCE,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($rows) {
            HrvSample::insert($rows);
        }
    }

    private function importHeartRate(User $user, string $date, ?array $heartRate): void
    {
        if (! $heartRate) {
            return;
        }

        if (isset($heartRate['restingHeartRate'])) {
            $this->putVital($user, 'resting_heart_rate', (float) $heartRate['restingHeartRate'], 'bpm', $date);
        }

        $values = $heartRate['heartRateValues'] ?? [];
        if (empty($values)) {
            return;
        }

        HeartRateSample::where('user_id', $user->id)
            ->where('source', self::SOURCE)
            ->whereDate('timestamp', $date)
            ->delete();

        $rows = [];
        $now = now();
        foreach ($values as $pair) {
            if (! is_array($pair) || count($pair) < 2 || $pair[1] === null) {
                continue;
            }
            $rows[] = [
                'user_id' => $user->id,
                'timestamp' => Carbon::createFromTimestampMs($pair[0]),
                'bpm' => $pair[1],
                'is_resting' => false,
                'source' => self::SOURCE,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($rows) {
            foreach (array_chunk($rows, 500) as $chunk) {
                HeartRateSample::insert($chunk);
            }
        }
    }

    private function importStress(User $user, string $date, ?array $stress): void
    {
        if (! $stress) {
            return;
        }

        if (isset($stress['avgStressLevel']) && $stress['avgStressLevel'] >= 0) {
            $this->putVital($user, 'stress_avg', (float) $stress['avgStressLevel'], 'level', $date);
        }
    }

    private function importSpo2(User $user, string $date, ?array $spo2): void
    {
        if (! $spo2) {
            return;
        }

        $value = $spo2['averageSpO2'] ?? $spo2['avgSpo2Value'] ?? null;

        if ($value !== null) {
            $this->putVital($user, 'spo2_avg', (float) $value, 'percent', $date);
        }
    }

    private function importMaxMetrics(User $user, string $date, ?array $maxMetrics): void
    {
        if (! $maxMetrics) {
            return;
        }

        $generic = $maxMetrics['generic'] ?? ($maxMetrics[0]['generic'] ?? null);
        $vo2max = $generic['vo2MaxPreciseValue'] ?? $generic['vo2MaxValue'] ?? null;

        if ($vo2max !== null) {
            $this->putVital($user, 'vo2max', (float) $vo2max, 'ml/kg/min', $date);
        }
    }

    private function importRespiration(User $user, string $date, ?array $respiration): void
    {
        if (! $respiration) {
            return;
        }

        $value = $respiration['avgWakingRespirationValue']
            ?? $respiration['avgSleepRespirationValue']
            ?? null;

        if ($value !== null) {
            $this->putVital($user, 'respiration_rate', (float) $value, 'breaths/min', $date);
        }
    }

    private function importTrainingReadiness(User $user, string $date, ?array $trainingReadiness): void
    {
        $entry = $trainingReadiness[0] ?? null;
        $score = $entry['score'] ?? null;

        if ($score !== null) {
            $this->putVital($user, 'training_readiness', (float) $score, 'score', $date);
        }

        $recoveryTime = $entry['recoveryTime'] ?? null;
        if ($recoveryTime !== null) {
            $this->putVital($user, 'recovery_time', (float) $recoveryTime, 'minutes', $date);
        }
    }

    private function importBodyBattery(User $user, array $bodyBatteryDays): void
    {
        foreach ($bodyBatteryDays as $day) {
            $date = $day['date'] ?? null;
            if (! $date) {
                continue;
            }

            if (isset($day['charged'])) {
                $this->putVital($user, 'body_battery_charged', (float) $day['charged'], 'points', $date);
            }
            if (isset($day['drained'])) {
                $this->putVital($user, 'body_battery_drained', (float) $day['drained'], 'points', $date);
            }
        }
    }

    private function importPersonalRecords(User $user, mixed $personalRecords): void
    {
        $records = is_array($personalRecords) ? $personalRecords : [];

        // Some accounts return a flat list, others nest it under a key — handle both.
        if (isset($records['personalRecords']) && is_array($records['personalRecords'])) {
            $records = $records['personalRecords'];
        }

        foreach ($records as $record) {
            if (! is_array($record) || ! isset($record['value'], $record['typeId'])) {
                continue;
            }

            $type = self::PR_TYPE_LABELS[$record['typeId']] ?? 'garmin_pr_type_'.$record['typeId'];

            $achievedDate = isset($record['prStartTimeGmt'])
                ? Carbon::createFromTimestampMs($record['prStartTimeGmt'])->toDateString()
                : now()->toDateString();

            PersonalRecord::updateOrCreate(
                ['user_id' => $user->id, 'type' => $type, 'achieved_date' => $achievedDate],
                [
                    'value' => $record['value'],
                    'unit' => ($record['activityType'] ?? 'unknown').'_raw',
                ]
            );
        }
    }

    private function importBodyComposition(User $user, ?array $bodyComposition): void
    {
        $entries = $bodyComposition['dateWeightList'] ?? [];

        foreach ($entries as $entry) {
            $calendarDate = $entry['calendarDate'] ?? null;
            if (! $calendarDate || ! isset($entry['weight'])) {
                continue;
            }

            BodyMeasurement::where('user_id', $user->id)
                ->where('source', self::SOURCE)
                ->whereDate('date', $calendarDate)
                ->delete();

            BodyMeasurement::create([
                'user_id' => $user->id,
                'date' => $calendarDate,
                'weight_kg' => $entry['weight'] / 1000,
                'bmi' => $entry['bmi'] ?? null,
                'body_fat_percent' => $entry['bodyFat'] ?? null,
                'source' => self::SOURCE,
            ]);
        }
    }

    private function importActivities(User $user, array $activities): void
    {
        foreach ($activities as $activity) {
            if (empty($activity['startTimeLocal']) || empty($activity['duration'])) {
                continue;
            }

            $start = Carbon::parse($activity['startTimeLocal']);
            $end = $start->copy()->addSeconds((int) $activity['duration']);

            Workout::where('user_id', $user->id)
                ->where('source', self::SOURCE)
                ->where('start_date', $start)
                ->delete();

            $avgSpeed = $activity['averageSpeed'] ?? null;
            $pace = ($avgSpeed && $avgSpeed > 0) ? 1000 / $avgSpeed : null;

            $workout = Workout::create([
                'user_id' => $user->id,
                'type' => $activity['activityType']['typeKey'] ?? 'unknown',
                'name' => $activity['activityName'] ?? null,
                'start_date' => $start,
                'end_date' => $end,
                'distance_meters' => $activity['distance'] ?? null,
                'active_calories' => $activity['calories'] ?? null,
                'average_heart_rate' => $activity['averageHR'] ?? null,
                'max_heart_rate' => $activity['maxHR'] ?? null,
                'average_pace_seconds_per_km' => $pace,
                'elevation_gain_meters' => $activity['elevationGain'] ?? null,
                'elevation_loss_meters' => $activity['elevationLoss'] ?? null,
                'training_effect_aerobic' => $activity['aerobicTrainingEffect'] ?? null,
                'training_effect_anaerobic' => $activity['anaerobicTrainingEffect'] ?? null,
                'training_effect_label' => $activity['trainingEffectLabel'] ?? null,
                'training_load' => $activity['activityTrainingLoad'] ?? null,
                'source' => self::SOURCE,
            ]);

            $this->importWorkoutSamples($workout, $activity['details'] ?? null);
            $this->importLaps($workout, $activity['laps'] ?? null);
        }
    }

    private function importWorkoutSamples(Workout $workout, ?array $details): void
    {
        $descriptors = $details['metricDescriptors'] ?? [];
        $metrics = $details['activityDetailMetrics'] ?? [];

        if (empty($descriptors) || empty($metrics)) {
            return;
        }

        $indexFor = [];
        foreach ($descriptors as $descriptor) {
            if (isset($descriptor['key'], $descriptor['metricsIndex'])) {
                $indexFor[$descriptor['key']] = $descriptor['metricsIndex'];
            }
        }

        $tsIdx = $indexFor['directTimestamp'] ?? null;
        if ($tsIdx === null) {
            return;
        }

        $hrIdx = $indexFor['directHeartRate'] ?? null;
        $speedIdx = $indexFor['directSpeed'] ?? null;
        $elevationIdx = $indexFor['directElevation'] ?? null;
        $cadenceIdx = $indexFor['directDoubleCadence'] ?? $indexFor['directRunCadence'] ?? null;
        $latIdx = $indexFor['directLatitude'] ?? null;
        $lngIdx = $indexFor['directLongitude'] ?? null;

        $rows = [];
        $now = now();

        foreach ($metrics as $point) {
            $values = $point['metrics'] ?? null;
            if (! is_array($values) || ! isset($values[$tsIdx])) {
                continue;
            }

            $speed = $speedIdx !== null ? ($values[$speedIdx] ?? null) : null;

            $rows[] = [
                'workout_id' => $workout->id,
                'timestamp' => Carbon::createFromTimestampMs($values[$tsIdx]),
                'heart_rate' => $hrIdx !== null ? ($values[$hrIdx] ?? null) : null,
                'pace_seconds_per_km' => ($speed && $speed > 0) ? 1000 / $speed : null,
                'altitude_meters' => $elevationIdx !== null ? ($values[$elevationIdx] ?? null) : null,
                'cadence' => $cadenceIdx !== null ? ($values[$cadenceIdx] ?? null) : null,
                'latitude' => $latIdx !== null ? ($values[$latIdx] ?? null) : null,
                'longitude' => $lngIdx !== null ? ($values[$lngIdx] ?? null) : null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($rows) {
            foreach (array_chunk($rows, 500) as $chunk) {
                WorkoutSample::insert($chunk);
            }
        }
    }

    private function importLaps(Workout $workout, ?array $lapsPayload): void
    {
        $laps = $lapsPayload['lapDTOs'] ?? [];

        if (empty($laps)) {
            return;
        }

        WorkoutLap::where('workout_id', $workout->id)->delete();

        $rows = [];
        $now = now();

        foreach ($laps as $index => $lap) {
            if (! isset($lap['startTimeGMT'])) {
                continue;
            }

            $speed = $lap['averageSpeed'] ?? null;
            $pace = ($speed && $speed > 0) ? 1000 / $speed : null;

            $rows[] = [
                'workout_id' => $workout->id,
                'lap_index' => $lap['lapIndex'] ?? ($index + 1),
                'start_time' => Carbon::parse($lap['startTimeGMT']),
                'distance_meters' => $lap['distance'] ?? null,
                'duration_seconds' => $lap['duration'] ?? null,
                'elevation_gain_meters' => $lap['elevationGain'] ?? null,
                'elevation_loss_meters' => $lap['elevationLoss'] ?? null,
                'average_heart_rate' => $lap['averageHR'] ?? null,
                'max_heart_rate' => $lap['maxHR'] ?? null,
                'average_pace_seconds_per_km' => $pace,
                'calories' => $lap['calories'] ?? null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($rows) {
            WorkoutLap::insert($rows);
        }
    }

    private function putVital(User $user, string $type, float $value, string $unit, string $date): void
    {
        VitalMeasurement::where('user_id', $user->id)
            ->where('source', self::SOURCE)
            ->where('type', $type)
            ->whereDate('date', $date)
            ->delete();

        VitalMeasurement::create([
            'user_id' => $user->id,
            'type' => $type,
            'value' => $value,
            'unit' => $unit,
            'date' => $date,
            'source' => self::SOURCE,
        ]);
    }
}
