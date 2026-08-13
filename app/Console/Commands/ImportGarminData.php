<?php

namespace App\Console\Commands;

use App\Models\ActivitySummary;
use App\Models\BodyMeasurement;
use App\Models\HeartRateSample;
use App\Models\HrvSample;
use App\Models\SleepSession;
use App\Models\User;
use App\Models\VitalMeasurement;
use App\Models\Workout;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportGarminData extends Command
{
    protected $signature = 'garmin:import {path : JSON file from garmin_sync.py, or - for stdin} {--user= : Target user ID (defaults to the first user)}';

    protected $description = 'Import health data pulled from Garmin Connect (via scripts/garmin_sync.py) into the RAGA database';

    private const SOURCE = 'garmin';

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
        $this->importActivities($user, $payload['activities'] ?? []);

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

            Workout::create([
                'user_id' => $user->id,
                'type' => $activity['activityType']['typeKey'] ?? 'unknown',
                'start_date' => $start,
                'end_date' => $end,
                'distance_meters' => $activity['distance'] ?? null,
                'active_calories' => $activity['calories'] ?? null,
                'average_heart_rate' => $activity['averageHR'] ?? null,
                'max_heart_rate' => $activity['maxHR'] ?? null,
                'average_pace_seconds_per_km' => $pace,
                'elevation_gain_meters' => $activity['elevationGain'] ?? null,
                'source' => self::SOURCE,
            ]);
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
