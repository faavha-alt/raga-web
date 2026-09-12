<?php

declare(strict_types=1);

namespace App\Services\Suunto;

use Illuminate\Support\Carbon;

/**
 * Mapper jalur TIDAK RESMI Suunto lewat CLI `suuntool`
 * (https://github.com/tajchert/suuntool, Go) yang memakai API privat aplikasi
 * Suunto. Berbeda dari {@see SuuntoWorkoutMapper} (Suunto Cloud API v3), kelas
 * ini memetakan bentuk JSON suuntool apa adanya ke payload yang dibaca
 * `App\Console\Commands\ImportGarminData`
 * (importActivities/importWorkoutSamples/importLaps).
 *
 * Bentuk JSON NYATA (diverifikasi dari test/go suuntool, bukan tebakan):
 *
 * 1. `GET /v1/workouts` dan `GET /v1/workouts/{key}` — envelope
 *    `{"error":null,"payload":...,"metadata":{"until":<ms>}}`. Satu workout:
 *    `key, username, activityId (int), startTime (unix ms), stopTime (unix ms),
 *    totalTime (DETIK), totalDistance (METER), totalAscent, totalDescent,
 *    maxSpeed, polyline, stepCount, recoveryTime, energyConsumption,
 *    hrdata{max,hrmax,avg,userMaxHR,workoutAvgHR,workoutMaxHR},
 *    tss{trainingStressScore,calculationMethod,intensityFactor,normalizedPower,
 *    averageGradeAdjustedPace}, tssList[], startPosition/stopPosition/
 *    centerPosition{latitude,longitude}, extensions[{type,vo2Max,fitnessAge} |
 *    {type,avgPower,peakEpoc}]`. Output tool MCP menambahkan `activityName`
 *    berupa enum UPPER_SNAKE (mis. `RUNNING`, `TRAIL_RUNNING`) dari `activityId`.
 *    Tidak ada blok lap pada payload ini → `laps` selalu null.
 *
 * 2. `GET /v1/workouts/{key}/sml` — stream per-sampel:
 *    `{"Data":{"Samples":[{"TimeISO8601":"...Z","Source":"...",
 *    "Attributes":{"suunto/sml":{"Sample":{...}}}}]},"Summary":{...}}`.
 *    Inner `Sample` memuat kanal: `HR, Power, Cadence, GPSAltitude, Latitude,
 *    Longitude, UTC, EHPE, NumberOfSatellites, Events`. Tool MCP dengan
 *    `streams`/`downsample` mengembalikan bentuk pipih
 *    `{"samples":[{"TimeISO8601","HR",...}]}` — keduanya didukung di sini.
 *    Importer hanya membaca `directTimestamp` (MILIDETIK), `directHeartRate`,
 *    `directSpeed`, `directElevation`, `directDoubleCadence`,
 *    `directLatitude`, `directLongitude`; `Power`/`EHPE`/`Events` tidak
 *    disimpan karena tidak ada kolomnya.
 *
 * 3. NDJSON wellness (`GET /v1/{sleep|activity|recovery}/export?since=<ms>`,
 *    gzip): satu baris
 *    `{"timestamp":"<RFC3339>","entryData":{duration, deepSleepDuration,
 *    lightSleepDuration, remSleepDuration, hrAvg, hrMin, quality, maxSpo2,
 *    avgHrv, isNap, sleepId}}`. Satuan: `hrAvg`/`hrMin` dalam Hz (×60 = BPM),
 *    `quality`/`maxSpo2` fraksi 0..1, semua durasi DETIK.
 */
class SuuntoToolMapper
{
    /** Di bawah ambang ini angka waktu dianggap detik, bukan milidetik. */
    private const EPOCH_MS_THRESHOLD = 100_000_000_000;

    /** Durasi > 48 jam dianggap milidetik (payload suuntool memakai detik). */
    private const DURATION_MS_THRESHOLD = 172_800;

    /** Kanal Sample SML → nama descriptor Garmin yang dibaca importer. */
    private const SML_METRICS = [
        'directHeartRate' => ['HR', 'HeartRate'],
        'directSpeed' => ['Speed', 'GPSpeed', 'GroundSpeed'],
        'directElevation' => ['GPSAltitude', 'Altitude'],
        'directDoubleCadence' => ['Cadence'],
        'directLatitude' => ['Latitude'],
        'directLongitude' => ['Longitude'],
    ];

    /**
     * `activityId` Suunto → enum APK (dikompilasi dari
     * suuntool/internal/api/endpoints/activity_types.go). Dipakai hanya bila
     * payload tidak membawa nama olahraga sama sekali.
     */
    private const ACTIVITY_ID_NAMES = [
        0 => 'WALKING',
        1 => 'RUNNING',
        2 => 'CYCLING',
        10 => 'MOUNTAIN_BIKING',
        11 => 'HIKING',
        13 => 'DOWNHILL_SKIING',
        15 => 'ROWING',
        17 => 'INDOOR',
        21 => 'SWIMMING',
        22 => 'TRAIL_RUNNING',
        23 => 'GYM',
        29 => 'CLIMBING',
        30 => 'SNOWBOARDING',
        51 => 'YOGA',
        52 => 'INDOOR_CYCLING',
        53 => 'TREADMILL',
        54 => 'CROSSFIT',
        55 => 'CROSSTRAINER',
        57 => 'INDOOR_ROWING',
        61 => 'SUP',
        68 => 'MULTISPORT',
        70 => 'TREKKING',
        72 => 'KAYAKING',
        74 => 'TRIATHLON',
        85 => 'OPENWATER_SWIMMING',
        99 => 'GRAVEL_CYCLING',
        103 => 'TRACK_RUNNING',
        105 => 'E_BIKING',
        106 => 'E_MTB',
        114 => 'CYCLOCROSS',
        115 => 'VERTICAL_RUN',
    ];

    /** Sinonim olahraga (bentuk tanpa underscore) → typeKey gaya Garmin. */
    private const TYPE_SYNONYMS = [
        'trailrunning' => 'trail_running',
        'trailrun' => 'trail_running',
        'run' => 'running',
        'roadrunning' => 'running',
        'treadmillrunning' => 'treadmill_running',
        'indoorrunning' => 'treadmill_running',
        'treadmill' => 'treadmill_running',
        'biking' => 'cycling',
        'roadcycling' => 'road_biking',
        'mtb' => 'mountain_biking',
        'mountainbiking' => 'mountain_biking',
        'indoorcycling' => 'indoor_cycling',
        'poolswimming' => 'lap_swimming',
        'lapswimming' => 'lap_swimming',
        'openwaterswimming' => 'open_water_swimming',
        'strengthtraining' => 'strength_training',
        'gym' => 'strength_training',
        'trekking' => 'hiking',
        'crosscountryskiing' => 'cross_country_skiing',
        'multisport' => 'multi_sport',
        'paddling' => 'paddling',
        'kayaking' => 'kayaking',
        'rowing' => 'rowing',
    ];

    /**
     * Ringkasan workout suuntool → payload importer.
     *
     * @param  array<string, mixed>  $workout  payload `GET /v1/workouts[/{key}]`
     * @param  array<string, mixed>|null  $sml  isi `/v1/workouts/{key}/sml`
     * @return array<string, mixed>
     */
    public function toGarminActivity(array $workout, ?array $sml = null): array
    {
        $parsed = $this->parseSml($sml);
        $timezone = (string) (config('app.timezone') ?: 'UTC');

        $start = $this->resolveStartTime($workout, $parsed, $timezone);
        $duration = $this->resolveDuration($workout, $parsed);
        $distance = $this->resolveDistance($workout);

        [$averageHR, $maxHR] = $this->resolveHeartRate($workout, $parsed);

        return [
            'startTimeLocal' => $start?->format('Y-m-d H:i:s'),
            'duration' => $duration,
            'distance' => $distance,
            'calories' => $this->resolveNumeric($workout, ['energyConsumption', 'calories', 'totalCalories']),
            'averageHR' => $averageHR,
            'maxHR' => $maxHR,
            'averageSpeed' => $this->resolveAverageSpeed($workout, $distance, $duration),
            'elevationGain' => $this->resolveNumeric($workout, ['totalAscent', 'elevationGain', 'ascent']),
            'elevationLoss' => $this->resolveNumeric($workout, ['totalDescent', 'elevationLoss', 'descent']),
            'activityType' => ['typeKey' => $this->resolveTypeKey($workout)],
            'activityName' => $this->resolveString($workout, ['activityName', 'name', 'description']),
            'aerobicTrainingEffect' => $this->resolveNumeric($workout, ['aerobicTrainingEffect', 'aerobicTE']),
            'anaerobicTrainingEffect' => $this->resolveNumeric($workout, ['anaerobicTrainingEffect', 'anaerobicTE']),
            'trainingEffectLabel' => $this->resolveString($workout, ['trainingEffectLabel', 'teLabel']),
            // Suunto memakai TSS sebagai ukuran beban latihan; importer menyimpannya
            // sebagai activityTrainingLoad.
            'activityTrainingLoad' => $this->resolveNumeric($workout, ['activityTrainingLoad', 'trainingLoad'])
                ?? $this->resolveNumeric($this->asArray($workout['tss'] ?? null), ['trainingStressScore']),
            'details' => $this->buildDetails($parsed),
            // Payload suuntool tidak membawa data lap — jangan menebak.
            'laps' => null,
        ];
    }

    /**
     * Entri NDJSON `sleep` → daftar atribut siap `SleepSession::create()`.
     *
     * - `sleepId` dipakai untuk dedupe; versi dengan `duration` terpanjang menang
     *   (stream 247 bersifat append-only dan mengirim update berkali-kali).
     * - Entri `isNap: true` dibuang bila field `isNap` ada.
     * - `bedtime` = `timestamp`, `wake_time` = bedtime + `duration` detik.
     *   Entri tanpa timestamp/durasi valid dilewati, bukan ditebak.
     * - `core_minutes` = light sleep; `awake_minutes` = sisa durasi di luar
     *   REM+deep+light (0 bila komponen tidak lengkap).
     * - `sleep_score` dari `quality` (0..1) → 0..100; quality 0 dianggap tidak
     *   diketahui → null.
     *
     * @param  array<int, array<string, mixed>>  $entries
     * @return array<int, array<string, mixed>>
     */
    public function sleepSessions(array $entries, int $userId, string $source = 'suunto'): array
    {
        $timezone = (string) (config('app.timezone') ?: 'UTC');

        /** @var array<int, array<string, mixed>> $deduped */
        $deduped = [];
        /** @var array<int|string, int> $indexByKey */
        $indexByKey = [];

        foreach ($entries as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $data = $this->asArray($entry['entryData'] ?? null);

            // Buang nap hanya bila field isNap benar-benar ada.
            if (array_key_exists('isNap', $data) && $this->toBool($data['isNap'])) {
                continue;
            }

            $sleepId = $this->toInt($data['sleepId'] ?? null);

            if ($sleepId !== null) {
                if (array_key_exists($sleepId, $indexByKey)) {
                    $existing = $deduped[$indexByKey[$sleepId]];
                    if ($this->toNumber($data['duration'] ?? null) > $this->toNumber($this->asArray($existing['entryData'] ?? null)['duration'] ?? null)) {
                        $deduped[$indexByKey[$sleepId]] = $entry;
                    }

                    continue;
                }

                $indexByKey[$sleepId] = count($deduped);
            }

            $deduped[] = $entry;
        }

        $sessions = [];

        foreach ($deduped as $entry) {
            $data = $this->asArray($entry['entryData'] ?? null);

            $bedtime = $this->parseTimestamp($entry['timestamp'] ?? null, $timezone);
            $duration = $this->toNumber($data['duration'] ?? null);

            if ($bedtime === null || $duration === null || $duration <= 0) {
                continue;
            }

            $remSeconds = $this->toNumber($data['remSleepDuration'] ?? null) ?? 0.0;
            $deepSeconds = $this->toNumber($data['deepSleepDuration'] ?? null) ?? 0.0;
            $lightSeconds = $this->toNumber($data['lightSleepDuration'] ?? null) ?? 0.0;
            $awakeSeconds = max(0.0, $duration - ($remSeconds + $deepSeconds + $lightSeconds));

            $quality = $this->toNumber($data['quality'] ?? null);
            $score = ($quality !== null && $quality > 0)
                ? max(0, min(255, (int) round($quality * 100)))
                : null;

            $sessions[] = [
                'user_id' => $userId,
                'bedtime' => $bedtime->format('Y-m-d H:i:s'),
                'wake_time' => $bedtime->copy()->addSeconds((int) round($duration))->format('Y-m-d H:i:s'),
                'rem_minutes' => round($remSeconds / 60, 2),
                'deep_minutes' => round($deepSeconds / 60, 2),
                'core_minutes' => round($lightSeconds / 60, 2),
                'awake_minutes' => round($awakeSeconds / 60, 2),
                'sleep_score' => $score,
                'source' => $source,
            ];
        }

        return $sessions;
    }

    /**
     * Nama olahraga bebas → typeKey gaya Garmin.
     *
     * Contoh: `TrailRunning` → `trail_running`, `MTB` → `mountain_biking`,
     * `OpenWaterSwimming` → `open_water_swimming`, `Road Cycling` → `road_biking`.
     */
    public function normalizeSport(string $name): string
    {
        $trimmed = trim($name);

        if ($trimmed === '') {
            return 'unknown';
        }

        // CamelCase → Camel_Case supaya batas kata tidak hilang.
        $spaced = preg_replace('/(?<=[a-z0-9])(?=[A-Z])/', '_', $trimmed) ?? $trimmed;
        $key = strtolower($spaced);
        $key = preg_replace('/[^a-z0-9]+/', '_', $key) ?? $key;
        $key = trim($key, '_');

        if ($key === '') {
            return 'unknown';
        }

        $compact = str_replace('_', '', $key);

        return self::TYPE_SYNONYMS[$compact] ?? $key;
    }

    // ---------------------------------------------------------------------
    // Parsing /sml
    // ---------------------------------------------------------------------

    /**
     * Parse sampel SML (bentuk mentah `Data.Samples` atau bentuk pipih MCP).
     *
     * @param  array<string, mixed>|null  $sml
     * @return array{points: array<int, array{ts: int, metrics: array<string, float|int>}>, hr: array<int, int>, firstTs: int|null, lastTs: int|null}
     */
    private function parseSml(?array $sml): array
    {
        $points = [];
        $hrValues = [];
        $firstTs = null;
        $lastTs = null;

        foreach ($this->smlSamples($sml) as $entry) {
            [$inner, $timeIso] = $this->unwrapSmlSample($entry);

            if ($inner === null) {
                continue;
            }

            $ts = $this->resolveSampleTimestamp($inner, $timeIso);

            if ($ts === null) {
                continue;
            }

            $metrics = [];
            foreach (self::SML_METRICS as $descriptor => $candidates) {
                $value = $this->resolveNumeric($inner, $candidates);

                if ($value !== null) {
                    $metrics[$descriptor] = $value;
                }
            }

            $hr = $this->resolveNumeric($inner, ['HR', 'HeartRate']);
            if ($hr !== null && $hr > 0) {
                $hrValues[] = (int) round($hr);
            }

            $points[] = ['ts' => $ts, 'metrics' => $metrics];
            $firstTs ??= $ts;
            $lastTs = $ts;
        }

        return [
            'points' => $points,
            'hr' => $hrValues,
            'firstTs' => $firstTs,
            'lastTs' => $lastTs,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $sml
     * @return array<int, array<string, mixed>>
     */
    private function smlSamples(?array $sml): array
    {
        if ($sml === null) {
            return [];
        }

        $raw = $sml['Data']['Samples'] ?? null;
        if (is_array($raw)) {
            return array_values(array_filter($raw, 'is_array'));
        }

        $flat = $sml['samples'] ?? null;
        if (is_array($flat)) {
            return array_values(array_filter($flat, 'is_array'));
        }

        // Diasumsikan sudah berupa list sampel.
        return array_values(array_filter($sml, 'is_array'));
    }

    /**
     * Ambil `Sample` dari `Attributes["suunto/sml"]`, atau pakai entri pipih.
     *
     * @param  array<string, mixed>  $entry
     * @return array{0: array<string, mixed>|null, 1: string|null}
     */
    private function unwrapSmlSample(array $entry): array
    {
        $timeIso = isset($entry['TimeISO8601']) && is_string($entry['TimeISO8601']) ? $entry['TimeISO8601'] : null;

        $inner = $entry['Attributes']['suunto/sml']['Sample'] ?? null;
        if (is_array($inner)) {
            return [$inner, $timeIso];
        }

        // Bentuk pipih (output MCP terfilter): field Sample sudah di level atas.
        // Baris metadata-only (hanya TimeISO8601/Source) tidak dianggap sampel.
        unset($entry['TimeISO8601']);
        $known = array_merge(
            ['UTC', 'Epoch', 'Time', 'timestamp'],
            ...array_values(self::SML_METRICS),
        );

        foreach ($known as $key) {
            if (array_key_exists($key, $entry)) {
                return [$entry, $timeIso];
            }
        }

        return [null, $timeIso];
    }

    /**
     * Timestamp sampel → milidetik. `UTC` inner bisa detik/ms/ISO; fallback
     * `TimeISO8601`.
     *
     * @param  array<string, mixed>  $inner
     */
    private function resolveSampleTimestamp(array $inner, ?string $timeIso): ?int
    {
        foreach (['UTC', 'Epoch', 'Time', 'timestamp'] as $key) {
            $value = $inner[$key] ?? null;

            if (is_numeric($value)) {
                $number = (float) $value;

                return $number >= self::EPOCH_MS_THRESHOLD ? (int) round($number) : (int) round($number * 1000);
            }

            if (is_string($value) && $value !== '') {
                $parsed = $this->parseTimestamp($value, 'UTC');

                if ($parsed !== null) {
                    return $parsed->getTimestampMs();
                }
            }
        }

        if ($timeIso !== null) {
            $parsed = $this->parseTimestamp($timeIso, 'UTC');

            return $parsed?->getTimestampMs();
        }

        return null;
    }

    /**
     * @param  array{points: array<int, array{ts: int, metrics: array<string, float|int>}>, hr: array<int, int>, firstTs: int|null, lastTs: int|null}  $parsed
     * @return array{metricDescriptors: array<int, array{key: string, metricsIndex: int}>, activityDetailMetrics: array<int, array{metrics: array<int, float|int|null>}>}|null
     */
    private function buildDetails(array $parsed): ?array
    {
        if ($parsed['points'] === []) {
            return null;
        }

        // Descriptor hanya untuk metrik yang benar-benar muncul di sampel.
        $present = [];
        foreach ($parsed['points'] as $point) {
            foreach (array_keys($point['metrics']) as $descriptor) {
                $present[$descriptor] = true;
            }
        }

        $order = array_values(array_filter(
            array_keys(self::SML_METRICS),
            static fn (string $descriptor): bool => isset($present[$descriptor]),
        ));

        $descriptors = [['key' => 'directTimestamp', 'metricsIndex' => 0]];
        $indexFor = [];
        $next = 1;

        foreach ($order as $descriptor) {
            $descriptors[] = ['key' => $descriptor, 'metricsIndex' => $next];
            $indexFor[$descriptor] = $next;
            $next++;
        }

        $rows = [];
        foreach ($parsed['points'] as $point) {
            // Isi semua indeks dengan null agar posisi metrik tidak bergeser.
            $values = array_fill(0, $next, null);
            $values[0] = $point['ts'];
            foreach ($indexFor as $descriptor => $index) {
                $values[$index] = $point['metrics'][$descriptor] ?? null;
            }
            $rows[] = ['metrics' => $values];
        }

        return [
            'metricDescriptors' => $descriptors,
            'activityDetailMetrics' => $rows,
        ];
    }

    // ---------------------------------------------------------------------
    // Ringkasan
    // ---------------------------------------------------------------------

    /**
     * @param  array{firstTs: int|null, lastTs: int|null}  $parsed
     */
    private function resolveStartTime(array $workout, array $parsed, string $timezone): ?Carbon
    {
        foreach (['startTime', 'startTimeLocal', 'startTimeGmt'] as $key) {
            $parsedTime = $this->parseTimestamp($workout[$key] ?? null, $timezone);

            if ($parsedTime !== null) {
                return $parsedTime;
            }
        }

        if ($parsed['firstTs'] !== null) {
            return Carbon::createFromTimestampMs($parsed['firstTs'], 'UTC')->setTimezone($timezone);
        }

        return null;
    }

    /**
     * @param  array{firstTs: int|null, lastTs: int|null}  $parsed
     */
    private function resolveDuration(array $workout, array $parsed): ?int
    {
        $duration = $this->resolveNumeric($workout, ['totalTime', 'duration', 'totalDuration', 'elapsedTime']);

        if ($duration !== null) {
            if ($duration > self::DURATION_MS_THRESHOLD) {
                $duration /= 1000;
            }

            return (int) max(0, (int) round($duration));
        }

        if ($parsed['firstTs'] !== null && $parsed['lastTs'] !== null) {
            return max(0, (int) round(($parsed['lastTs'] - $parsed['firstTs']) / 1000));
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $workout
     */
    private function resolveDistance(array $workout): ?float
    {
        return $this->resolveNumeric($workout, ['totalDistance', 'distance', 'distanceMeters']);
    }

    /**
     * HR dari `hrdata`; bila ringkasan tidak punya HR, hitung rata-rata dan
     * maksimum dari sampel `/sml` (dibulatkan ke BPM).
     *
     * @param  array<string, mixed>  $workout
     * @param  array{hr: array<int, int>}  $parsed
     * @return array{0: int|null, 1: int|null}
     */
    private function resolveHeartRate(array $workout, array $parsed): array
    {
        $hrdata = $this->asArray($workout['hrdata'] ?? null);

        $averageHR = $this->resolveInt($hrdata, ['workoutAvgHR', 'avg', 'averageHR', 'avgHR', 'averageHeartRate']);
        $maxHR = $this->resolveInt($hrdata, ['workoutMaxHR', 'max', 'hrmax', 'maxHR', 'maxHeartRate']);

        if ($parsed['hr'] !== []) {
            $averageHR ??= (int) round(array_sum($parsed['hr']) / count($parsed['hr']));
            $maxHR ??= max($parsed['hr']);
        }

        return [$averageHR, $maxHR];
    }

    /**
     * @param  array<string, mixed>  $workout
     */
    private function resolveAverageSpeed(array $workout, ?float $distance, ?int $duration): ?float
    {
        $speed = $this->resolveNumeric($workout, ['averageSpeed', 'avgSpeed']);

        if ($speed !== null) {
            return $speed;
        }

        if ($distance !== null && $distance > 0 && $duration !== null && $duration > 0) {
            return $distance / $duration;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $workout
     */
    private function resolveTypeKey(array $workout): string
    {
        $name = $this->resolveString($workout, ['activityName', 'sport', 'sportName', 'typeKey']);

        if ($name === null && isset($workout['activityType']) && is_string($workout['activityType'])) {
            $name = $workout['activityType'];
        }

        if ($name === null) {
            $id = $this->toInt($workout['activityId'] ?? null);

            if ($id !== null && isset(self::ACTIVITY_ID_NAMES[$id])) {
                $name = self::ACTIVITY_ID_NAMES[$id];
            }
        }

        return $name !== null ? $this->normalizeSport($name) : 'unknown';
    }

    // ---------------------------------------------------------------------
    // Helper tipis
    // ---------------------------------------------------------------------

    private function parseTimestamp(mixed $value, string $timezone): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            if (is_numeric($value)) {
                $number = (float) $value;

                return $number >= self::EPOCH_MS_THRESHOLD
                    ? Carbon::createFromTimestampMs((int) round($number), 'UTC')->setTimezone($timezone)
                    : Carbon::createFromTimestamp((int) round($number), 'UTC')->setTimezone($timezone);
            }

            if (is_string($value)) {
                return Carbon::parse($value, 'UTC')->setTimezone($timezone);
            }
        } catch (\Throwable) {
            return null;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $source
     * @param  array<int, string>  $keys
     */
    private function resolveNumeric(array $source, array $keys): ?float
    {
        foreach ($keys as $key) {
            if (! array_key_exists($key, $source)) {
                continue;
            }

            $number = $this->toNumber($source[$key]);

            if ($number !== null) {
                return $number;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $source
     * @param  array<int, string>  $keys
     */
    private function resolveInt(array $source, array $keys): ?int
    {
        $number = $this->resolveNumeric($source, $keys);

        return $number !== null ? (int) round($number) : null;
    }

    /**
     * @param  array<string, mixed>  $source
     * @param  array<int, string>  $keys
     */
    private function resolveString(array $source, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = $source[$key] ?? null;

            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return null;
    }

    private function toNumber(mixed $value): ?float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        if (is_string($value) && is_numeric(trim($value))) {
            return (float) trim($value);
        }

        return null;
    }

    private function toInt(mixed $value): ?int
    {
        $number = $this->toNumber($value);

        return $number !== null ? (int) round($number) : null;
    }

    private function toBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            return in_array(strtolower(trim($value)), ['1', 'true', 'yes'], true);
        }

        return (bool) $value;
    }

    /**
     * @return array<string, mixed>
     */
    private function asArray(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }
}
