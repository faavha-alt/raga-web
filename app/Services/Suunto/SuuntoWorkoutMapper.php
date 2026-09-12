<?php

declare(strict_types=1);

namespace App\Services\Suunto;

use Illuminate\Support\Carbon;

/**
 * Mapper read-only dari payload Suunto Cloud API v3 (`GET /v3/workouts` dan
 * `GET /v3/workouts/{id}`) ke bentuk payload yang dibaca
 * `App\Console\Commands\ImportGarminData` (importActivities/importWorkoutSamples/importLaps).
 *
 * Bentuk JSON persis Suunto v3 belum terverifikasi (butuh akun berlangganan),
 * jadi kelas ini sengaja TOLERAN: mencoba beberapa kandidat nama field dan
 * mengembalikan null bila tidak ada — tidak pernah menebak nilai.
 *
 * Kandidat key yang dicoba:
 * - id      : workoutKey / key / id
 * - waktu   : startTime / startTimeLocal / activityStartTime / startDate / startTimeGmt
 * - durasi  : duration / totalTime / totalDuration / elapsedTime
 * - jarak   : distance / totalDistance
 * - kalori  : calories / totalCalories / energyConsumption
 * - HR      : averageHR / avgHeartRate / averageHeartRate ; maxHR / maxHeartRate
 * - elevasi : elevationGain / totalAscent / ascent ; elevationLoss / totalDescent / descent
 * - kecepatan: averageSpeed / avgSpeed ; fallback terakhir distance ÷ duration
 * - nama    : activityName / name / description / sport
 * - sport   : activityType / sport / typeKey / sportName
 */
class SuuntoWorkoutMapper
{
    /** Ambang batas epoch milidetik (di bawah ini dianggap detik). */
    private const EPOCH_MS_THRESHOLD = 100_000_000_000;

    /** Durasi di atas ambang ini dianggap milidetik (mis. 3 jam = 10800000 ms). */
    private const DURATION_MS_THRESHOLD = 172_800; // 48 jam dalam detik

    /** Urutan descriptor Garmin-style yang didukung importer. */
    private const METRIC_KEYS = [
        'hr' => 'directHeartRate',
        'speed' => 'directSpeed',
        'elevation' => 'directElevation',
        'cadence' => 'directDoubleCadence',
        'lat' => 'directLatitude',
        'lon' => 'directLongitude',
    ];

    /** Sinonim olahraga Suunto → gaya Garmin (typeKey). */
    private const TYPE_SYNONYMS = [
        'trailrunning' => 'trail_running',
        'trailrun' => 'trail_running',
        'run' => 'running',
        'roadrunning' => 'running',
        'treadmillrunning' => 'treadmill_running',
        'indoorrunning' => 'treadmill_running',
        'biking' => 'cycling',
        'roadcycling' => 'road_biking',
        'mtb' => 'mountain_biking',
        'mountainbiking' => 'mountain_biking',
        'indoorcycling' => 'indoor_cycling',
        'poolswimming' => 'lap_swimming',
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
     * @param  array<string, mixed>  $workout
     * @return array<string, mixed>
     */
    public function toGarminActivity(array $workout): array
    {
        $start = $this->resolveStartTime($workout);
        $duration = $this->resolveDuration($workout);
        $distance = $this->resolveDistance($workout);
        $speed = $this->resolveAverageSpeed($workout, $distance, $duration);

        return [
            'startTimeLocal' => $start?->format('Y-m-d H:i:s'),
            'duration' => $duration,
            'distance' => $distance,
            'calories' => $this->resolveNumeric($workout, ['calories', 'totalCalories', 'energyConsumption']),
            'averageHR' => $this->resolveInt($workout, ['averageHR', 'avgHeartRate', 'averageHeartRate', 'avgHR']),
            'maxHR' => $this->resolveInt($workout, ['maxHR', 'maxHeartRate']),
            'averageSpeed' => $speed,
            'elevationGain' => $this->resolveNumeric($workout, ['elevationGain', 'totalAscent', 'ascent']),
            'elevationLoss' => $this->resolveNumeric($workout, ['elevationLoss', 'totalDescent', 'descent']),
            'activityType' => ['typeKey' => $this->resolveTypeKey($workout)],
            'activityName' => $this->resolveString($workout, ['activityName', 'name', 'description', 'sport']),
            'aerobicTrainingEffect' => $this->resolveNumeric($workout, ['aerobicTrainingEffect', 'aerobicTE']),
            'anaerobicTrainingEffect' => $this->resolveNumeric($workout, ['anaerobicTrainingEffect', 'anaerobicTE']),
            'trainingEffectLabel' => $this->resolveString($workout, ['trainingEffectLabel', 'teLabel']),
            'activityTrainingLoad' => $this->resolveNumeric($workout, ['activityTrainingLoad', 'trainingLoad']),
            'details' => $this->buildDetails($workout, $start),
            'laps' => $this->buildLaps($workout),
        ];
    }

    // ---------------------------------------------------------------------
    // Ringkasan
    // ---------------------------------------------------------------------

    /**
     * @param  array<string, mixed>  $workout
     */
    private function resolveStartTime(array $workout): ?Carbon
    {
        $timezone = config('app.timezone') ?: 'UTC';

        foreach (['startTimeLocal', 'startTime', 'activityStartTime', 'startDate', 'startTimeGmt'] as $key) {
            if (! isset($workout[$key]) || $workout[$key] === '') {
                continue;
            }

            $value = $workout[$key];
            $hintUtc = str_contains(strtolower($key), 'gmt') || str_contains(strtolower($key), 'utc');

            if (is_numeric($value)) {
                $number = (float) $value;
                if ($number >= self::EPOCH_MS_THRESHOLD) {
                    return Carbon::createFromTimestampMs((int) round($number), $timezone);
                }
                if ($number >= 1_000_000_000) {
                    return Carbon::createFromTimestamp((int) round($number), $timezone);
                }

                continue;
            }

            if (! is_string($value)) {
                continue;
            }

            try {
                $parsed = $hintUtc
                    ? Carbon::parse($value, 'UTC')
                    : Carbon::parse($value, $timezone);

                return $parsed->setTimezone($timezone);
            } catch (\Throwable) {
                continue;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $workout
     */
    private function resolveDuration(array $workout): ?int
    {
        $value = $this->resolveNumeric($workout, ['duration', 'totalTime', 'totalDuration', 'elapsedTime']);

        if ($value === null) {
            return null;
        }

        // Sebagian API mengirim durasi dalam milidetik.
        if ($value > self::DURATION_MS_THRESHOLD) {
            $value /= 1000;
        }

        return (int) round($value);
    }

    /**
     * @param  array<string, mixed>  $workout
     */
    private function resolveDistance(array $workout): ?float
    {
        return $this->resolveNumeric($workout, ['distance', 'totalDistance']);
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

        // Fallback terakhir: hitung dari jarak + durasi.
        if ($distance !== null && $duration !== null && $duration > 0) {
            return $distance / $duration;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $workout
     */
    private function resolveTypeKey(array $workout): string
    {
        $raw = null;

        foreach (['activityType', 'sport', 'typeKey', 'sportName', 'type', 'workoutType'] as $key) {
            if (! isset($workout[$key])) {
                continue;
            }

            $value = $workout[$key];
            if (is_array($value)) {
                $value = $value['typeKey'] ?? $value['key'] ?? $value['name'] ?? null;
            }

            if (is_string($value) && $value !== '') {
                $raw = $value;
                break;
            }
        }

        if ($raw === null) {
            return 'unknown';
        }

        $normalized = strtolower(trim($raw));
        $normalized = preg_replace('/[\s\-]+/', '_', $normalized) ?? $normalized;
        $normalized = preg_replace('/[^a-z0-9_]/', '', $normalized) ?? $normalized;

        if ($normalized === '') {
            return 'unknown';
        }

        $compact = str_replace('_', '', $normalized);

        return self::TYPE_SYNONYMS[$normalized] ?? self::TYPE_SYNONYMS[$compact] ?? $normalized;
    }

    // ---------------------------------------------------------------------
    // Streams / sampel → details Garmin-style
    // ---------------------------------------------------------------------

    /**
     * Mendukung tiga bentuk payload:
     *  (a) $workout['streams']    = list ['type'|'name' => ..., 'data'|'samples'|'values' => [...]]
     *  (b) $workout['extensions'] = assosiatif ['HeartrateStreamExtension' => list skalar / list map / ['samples' => [...]]]
     *  (c) $workout['samples']    = list peta per-sampel ['timestamp', 'heartRate', 'speed', ...]
     *
     * @param  array<string, mixed>  $workout
     */
    private function buildDetails(array $workout, ?Carbon $start): ?array
    {
        $points = [];          // tsMs => ['hr' => v, ...]
        $explicit = [];        // list ['metric' => string, 'ts' => int, 'value' => mixed]
        $pending = [];         // metric => list nilai (tanpa timestamp)
        $globalTs = [];        // list int ms dari stream waktu
        $startMs = $start?->getTimestampMs();

        // (a) streams
        foreach ($this->asList($workout['streams'] ?? null) as $stream) {
            if (! is_array($stream)) {
                continue;
            }

            $name = $stream['type'] ?? $stream['name'] ?? $stream['key'] ?? null;
            $payload = $stream['data'] ?? $stream['samples'] ?? $stream['values'] ?? null;
            $parallel = $stream['timestamps'] ?? $stream['time'] ?? null;

            $this->ingestSeries($name, $payload, $explicit, $pending, $globalTs, $startMs, $parallel);
        }

        // (b) extensions assosiatif (atau list entry ber-type)
        $extensions = $workout['extensions'] ?? null;
        if (is_array($extensions)) {
            foreach ($extensions as $key => $extension) {
                if (is_string($key)) {
                    $payload = is_array($extension) && isset($extension['samples']) && ! $this->isList($extension)
                        ? $extension['samples']
                        : $extension;

                    $this->ingestSeries($key, $payload, $explicit, $pending, $globalTs, $startMs, null);

                    continue;
                }

                if (is_array($extension)) {
                    $name = $extension['type'] ?? $extension['name'] ?? $extension['key'] ?? null;
                    $payload = $extension['data'] ?? $extension['samples'] ?? $extension['values'] ?? null;

                    $this->ingestSeries($name, $payload, $explicit, $pending, $globalTs, $startMs, $extension['timestamps'] ?? null);
                }
            }
        }

        // (c) samples list peta per-sampel
        foreach ($this->asList($workout['samples'] ?? $workout['samplesStream'] ?? null) as $sample) {
            if (! is_array($sample)) {
                continue;
            }

            $ts = $this->extractTimestamp(
                $sample['timestamp'] ?? $sample['time'] ?? $sample['t'] ?? $sample['ts'] ?? null,
                $startMs,
            );

            if ($ts === null) {
                continue;
            }

            foreach (['hr', 'speed', 'elevation', 'cadence', 'lat', 'lon'] as $metric) {
                $value = $this->extractMetricValue($sample, $metric);
                if ($value !== null) {
                    $points[$ts][$metric] = $value;
                }
            }
        }

        foreach ($explicit as $entry) {
            $points[$entry['ts']][$entry['metric']] = $entry['value'];
        }

        // Nilai tanpa timestamp dipasangkan berurutan dengan stream waktu global.
        $globalTs = array_values($globalTs);
        foreach ($pending as $metric => $values) {
            foreach ($values as $index => $value) {
                if (isset($globalTs[$index])) {
                    $points[$globalTs[$index]][$metric] = $value;
                }
            }
        }

        // Buang titik yang hanya berisi timestamp tanpa data apa pun.
        $points = array_filter($points, fn (array $values) => $values !== []);
        if ($points === []) {
            return null;
        }

        $present = [];
        foreach (array_keys(self::METRIC_KEYS) as $metric) {
            foreach ($points as $values) {
                if (array_key_exists($metric, $values) && $values[$metric] !== null) {
                    $present[] = $metric;
                    break;
                }
            }
        }

        if ($present === []) {
            return null;
        }

        $descriptors = [['key' => 'directTimestamp', 'metricsIndex' => 0]];
        $indexFor = [];
        foreach ($present as $offset => $metric) {
            $indexFor[$metric] = $offset + 1;
            $descriptors[] = ['key' => self::METRIC_KEYS[$metric], 'metricsIndex' => $offset + 1];
        }

        ksort($points, SORT_NUMERIC);

        $metrics = [];
        foreach ($points as $ts => $values) {
            $row = [$ts];
            foreach ($present as $metric) {
                $row[] = $values[$metric] ?? null;
            }
            $metrics[] = ['metrics' => $row];
        }

        return [
            'metricDescriptors' => $descriptors,
            'activityDetailMetrics' => $metrics,
        ];
    }

    /**
     * @param  array<int, array{metric: string, ts: int, value: mixed}>  $explicit
     * @param  array<string, list<mixed>>  $pending
     * @param  list<int>  $globalTs
     * @param  list<mixed>|null  $parallel
     */
    private function ingestSeries(
        mixed $name,
        mixed $payload,
        array &$explicit,
        array &$pending,
        array &$globalTs,
        ?int $startMs,
        ?array $parallel,
    ): void {
        if (! is_string($name) || $payload === null) {
            return;
        }

        $metric = $this->canonicalMetric($name);
        if ($metric === null) {
            return;
        }

        $items = $this->asList($payload);

        foreach ($items as $index => $item) {
            // Beberapa klien membungkus: ['samples' => [...]]
            if (is_array($item) && isset($item['samples']) && is_array($item['samples'])) {
                foreach ($this->asList($item['samples']) as $sample) {
                    $this->appendSample($metric, $sample, $parallel[$index] ?? null, $startMs, $explicit, $pending, $globalTs);
                }

                continue;
            }

            $this->appendSample($metric, $item, $parallel[$index] ?? null, $startMs, $explicit, $pending, $globalTs);
        }
    }

    /**
     * @param  array<int, array{metric: string, ts: int, value: mixed}>  $explicit
     * @param  array<string, list<mixed>>  $pending
     * @param  list<int>  $globalTs
     */
    private function appendSample(
        string $metric,
        mixed $item,
        mixed $parallelTs,
        ?int $startMs,
        array &$explicit,
        array &$pending,
        array &$globalTs,
    ): void {
        $ts = $parallelTs !== null ? $this->extractTimestamp($parallelTs, $startMs) : null;

        // Stream waktu bisa berisi list skalar epoch/offset, bukan map.
        if ($ts === null && $metric === 'time') {
            $ts = $this->extractTimestamp($item, $startMs);
        }

        if (is_array($item)) {
            $ts ??= $this->extractTimestamp($item, $startMs);

            // Bentuk pasangan [timestamp, value].
            if ($ts === null && $this->isNumericPair($item)) {
                $values = array_values($item);
                $ts = $this->extractTimestamp($values[0], $startMs);
                if ($ts !== null) {
                    $item = $values[1];
                }
            }
        }

        if ($metric === 'time') {
            if ($ts !== null) {
                $globalTs[] = $ts;
            }

            return;
        }

        // LocationStreamExtension membawa pasangan lat/lon sekaligus.
        if ($metric === 'location') {
            foreach (['lat', 'lon'] as $axis) {
                $coordinate = $this->extractMetricValue($item, $axis);
                if ($coordinate === null) {
                    continue;
                }

                if ($ts !== null) {
                    $explicit[] = ['metric' => $axis, 'ts' => $ts, 'value' => $coordinate];
                } else {
                    $pending[$axis][] = $coordinate;
                }
            }

            return;
        }

        $value = $this->extractMetricValue($item, $metric);
        if ($value === null) {
            return;
        }

        if ($ts !== null) {
            $explicit[] = ['metric' => $metric, 'ts' => $ts, 'value' => $value];
        } else {
            $pending[$metric][] = $value;
        }
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function extractMetricValue(mixed $item, string $metric): int|float|null
    {
        if (! is_array($item)) {
            return is_numeric($item) ? $this->toNumber($item) : null;
        }

        $keys = match ($metric) {
            'hr' => ['heartRate', 'heart_rate', 'hr', 'value'],
            'speed' => ['speed', 'velocity', 'value'],
            'elevation' => ['altitude', 'elevation', 'alt', 'value'],
            'cadence' => ['cadence', 'value'],
            'lat' => ['latitude', 'lat', 'value'],
            'lon' => ['longitude', 'lon', 'lng', 'long', 'value'],
            default => ['value'],
        };

        foreach ($keys as $key) {
            if (isset($item[$key]) && is_numeric($item[$key])) {
                return $this->toNumber($item[$key]);
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function extractTimestamp(mixed $item, ?int $startMs): ?int
    {
        if (is_array($item)) {
            foreach (['timestamp', 'time', 't', 'ts', 'date', 'startTime'] as $key) {
                if (isset($item[$key])) {
                    $ts = $this->extractTimestamp($item[$key], $startMs);
                    if ($ts !== null) {
                        return $ts;
                    }
                }
            }

            return null;
        }

        if (! is_numeric($item) && ! is_string($item)) {
            return null;
        }

        if (is_string($item)) {
            $trimmed = trim($item);
            if ($trimmed === '') {
                return null;
            }

            if (! preg_match('/^-?\d+(\.\d+)?$/', $trimmed)) {
                try {
                    return Carbon::parse($trimmed)->getTimestampMs();
                } catch (\Throwable) {
                    return null;
                }
            }

            $item = (float) $trimmed;
        }

        $number = (float) $item;

        if ($number >= self::EPOCH_MS_THRESHOLD) {
            return (int) round($number);
        }

        if ($number >= 1_000_000_000) {
            return (int) round($number * 1000);
        }

        // Angka kecil = offset detik relatif terhadap start workout.
        if ($startMs !== null) {
            return $startMs + (int) round($number * 1000);
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function canonicalMetric(mixed $name): ?string
    {
        if (! is_string($name)) {
            return null;
        }

        $normalized = strtolower(preg_replace('/[^a-z0-9]/i', '', $name) ?? '');

        return match (true) {
            str_contains($normalized, 'heartrate'), str_contains($normalized, 'heart_rate') => 'hr',
            $normalized === 'hr' => 'hr',
            str_contains($normalized, 'speed') => 'speed',
            str_contains($normalized, 'altitude'), str_contains($normalized, 'elevation') => 'elevation',
            str_contains($normalized, 'cadence') => 'cadence',
            $normalized === 'latitude', $normalized === 'lat' => 'lat',
            $normalized === 'longitude', $normalized === 'lon', $normalized === 'lng' => 'lon',
            str_contains($normalized, 'location'), str_contains($normalized, 'gps') => 'location',
            $normalized === 'time', str_contains($normalized, 'timestamp'), str_contains($normalized, 'timestream') => 'time',
            default => null,
        };
    }

    // ---------------------------------------------------------------------
    // Laps
    // ---------------------------------------------------------------------

    /**
     * @param  array<string, mixed>  $workout
     */
    private function buildLaps(array $workout): ?array
    {
        $raw = $workout['laps'] ?? $workout['lapDTOs'] ?? null;
        if (is_array($raw) && isset($raw['lapDTOs'])) {
            $raw = $raw['lapDTOs'];
        }

        $laps = [];

        foreach ($this->asList($raw) as $index => $lap) {
            if (! is_array($lap)) {
                continue;
            }

            $start = $this->parseLapStart($lap);
            if ($start === null) {
                continue;
            }

            $distance = $this->resolveNumeric($lap, ['distance', 'totalDistance']);
            $duration = $this->resolveDuration($lap);
            $speed = $this->resolveNumeric($lap, ['averageSpeed', 'avgSpeed']);
            if ($speed === null && $distance !== null && $duration !== null && $duration > 0) {
                $speed = $distance / $duration;
            }

            $laps[] = [
                'lapIndex' => $this->resolveInt($lap, ['lapIndex', 'index', 'lap']) ?? ($index + 1),
                // Importer mem-parse nilai ini sebagai UTC.
                'startTimeGMT' => $start,
                'distance' => $distance,
                'duration' => $duration,
                'elevationGain' => $this->resolveNumeric($lap, ['elevationGain', 'totalAscent', 'ascent']),
                'elevationLoss' => $this->resolveNumeric($lap, ['elevationLoss', 'totalDescent', 'descent']),
                'averageHR' => $this->resolveInt($lap, ['averageHR', 'avgHeartRate', 'averageHeartRate']),
                'maxHR' => $this->resolveInt($lap, ['maxHR', 'maxHeartRate']),
                'averageSpeed' => $speed,
                'calories' => $this->resolveNumeric($lap, ['calories', 'totalCalories', 'energyConsumption']),
            ];
        }

        return $laps === [] ? null : ['lapDTOs' => $laps];
    }

    /**
     * @param  array<string, mixed>  $lap
     */
    private function parseLapStart(array $lap): ?string
    {
        foreach (['startTimeGMT', 'startTime', 'startTimeLocal', 'startDate', 'timestamp', 'time'] as $key) {
            if (! isset($lap[$key]) || $lap[$key] === '') {
                continue;
            }

            $value = $lap[$key];

            if (is_numeric($value)) {
                $number = (float) $value;
                $carbon = $number >= self::EPOCH_MS_THRESHOLD
                    ? Carbon::createFromTimestampMs((int) round($number), 'UTC')
                    : ($number >= 1_000_000_000 ? Carbon::createFromTimestamp((int) round($number), 'UTC') : null);

                if ($carbon !== null) {
                    return $carbon->toIso8601String();
                }

                continue;
            }

            if (! is_string($value)) {
                continue;
            }

            try {
                return Carbon::parse($value, 'UTC')->setTimezone('UTC')->toIso8601String();
            } catch (\Throwable) {
                continue;
            }
        }

        return null;
    }

    // ---------------------------------------------------------------------
    // Helper
    // ---------------------------------------------------------------------

    /**
     * @param  array<string, mixed>  $source
     * @param  list<string>  $keys
     */
    private function resolveNumeric(array $source, array $keys): ?float
    {
        foreach ($keys as $key) {
            if (isset($source[$key]) && is_numeric($source[$key])) {
                return $this->toNumber($source[$key]);
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $source
     * @param  list<string>  $keys
     */
    private function resolveInt(array $source, array $keys): ?int
    {
        $value = $this->resolveNumeric($source, $keys);

        return $value === null ? null : (int) round($value);
    }

    /**
     * @param  array<string, mixed>  $source
     * @param  list<string>  $keys
     */
    private function resolveString(array $source, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (isset($source[$key]) && is_string($source[$key]) && trim($source[$key]) !== '') {
                return trim($source[$key]);
            }
        }

        return null;
    }

    private function toNumber(mixed $value): float
    {
        return (float) $value;
    }

    /**
     * @return list<mixed>
     */
    private function asList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        if ($this->isList($value)) {
            return array_values($value);
        }

        return [$value];
    }

    /**
     * @param  array<mixed>  $value
     */
    private function isList(array $value): bool
    {
        return array_is_list($value);
    }

    /**
     * @param  array<mixed>  $value
     */
    private function isNumericPair(array $value): bool
    {
        return count($value) === 2 && is_numeric(array_values($value)[0]);
    }
}
