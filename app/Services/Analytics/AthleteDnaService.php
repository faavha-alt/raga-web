<?php

namespace App\Services\Analytics;

use App\Models\PersonalRecord;
use App\Models\User;
use App\Services\Activity\ActivityQueryService;
use App\Services\Training\TrainingConsistencyService;
use Illuminate\Support\Carbon;

/**
 * Perhitungan halaman "Athlete DNA".
 *
 * Semua angka di sini berasal dari data pengguna sendiri (workout, recovery,
 * readiness, training load, VO2max, personal record) — tidak ada nilai contoh
 * atau angka Garmin yang disalin mentah. Setiap pilar radar mengembalikan null
 * ketika datanya tidak ada, supaya tampilan bisa jujur "belum ada data" alih-alih
 * menggambar nol yang menyesatkan.
 *
 * Query sengaja memakai agregasi GROUP BY / SUM / COUNT (bukan memuat model lalu
 * menjumlah di PHP) dan tidak menyentuh relasi secara lazy, sehingga aman
 * terhadap Model::preventLazyLoading() dan bebas N+1.
 */
class AthleteDnaService
{
    public const SUMMARY_DAYS = 30;

    public const GRID_DAYS = 364;

    public const COMPARISON_DAYS = 28;

    /** Urutan prioritas kartu Personal Records; sisanya diisi fallback Garmin. */
    private const PR_PRIORITY = [
        'fastest_1k',
        'fastest_1_mile',
        'fastest_5k',
        'fastest_10k',
        'fastest_half_marathon',
        'fastest_marathon',
        'longest_run',
    ];

    private const PR_CARD_LIMIT = 6;

    /** Ambang normalisasi VO2max (ml/kg/min) → skor 0–100. */
    private const VO2MAX_FLOOR = 30.0;

    private const VO2MAX_CEILING = 70.0;

    public function __construct(
        private TrainingConsistencyService $consistency,
        private ActivityQueryService $activityQuery,
    ) {}

    /**
     * Seluruh data halaman dalam satu panggilan.
     *
     * @return array{
     *     summary: array<string,mixed>,
     *     exertion: array<string,mixed>,
     *     discipline: array<string,mixed>,
     *     pillars: list<array{key:string,label:string,score:?int,source:string}>,
     *     comparison: list<array<string,mixed>>,
     *     records: list<array<string,mixed>>,
     * }
     */
    public function forUser(User $user, ?Carbon $today = null): array
    {
        $today = ($today ?? Carbon::today())->copy()->startOfDay();

        return [
            'summary' => $this->summary($user, $today),
            'exertion' => $this->exertionGrid($user, $today),
            'discipline' => $this->disciplineRatio($user, $today),
            'pillars' => $this->pillars($user, $today),
            'comparison' => $this->comparison($user, $today),
            'records' => $this->personalRecords($user),
        ];
    }

    /**
     * Metrik ringkas 30 hari terakhir.
     *
     * Pace dirata-ratakan dengan bobot jarak (pace × jarak / total jarak) supaya
     * sesi pendek tidak mendominasi; bila tidak ada jarak sama sekali, dipakai
     * rata-rata sederhana. Pace = null bila tak satu pun aktivitas punya pace.
     *
     * @return array{
     *     days:int,
     *     distance_meters:float,
     *     duration_seconds:int,
     *     activity_count:int,
     *     elevation_gain_meters:float,
     *     relative_effort:int,
     *     average_pace_seconds_per_km:?float,
     * }
     */
    public function summary(User $user, Carbon $today): array
    {
        $from = $today->copy()->subDays(self::SUMMARY_DAYS - 1);
        $durationExpr = $this->activityQuery->durationSqlExpression();

        $row = $user->workouts()
            ->whereBetween('start_date', [$from, $today->copy()->endOfDay()])
            ->selectRaw("SUM(distance_meters) as distance,
                SUM(elevation_gain_meters) as elevation,
                COUNT(*) as workout_count,
                SUM(ABS({$durationExpr})) as duration,
                SUM(relative_effort) as effort,
                SUM(CASE WHEN average_pace_seconds_per_km IS NOT NULL AND distance_meters > 0 THEN average_pace_seconds_per_km * distance_meters ELSE 0 END) as pace_weighted,
                SUM(CASE WHEN average_pace_seconds_per_km IS NOT NULL AND distance_meters > 0 THEN distance_meters ELSE 0 END) as pace_distance,
                AVG(average_pace_seconds_per_km) as pace_mean")
            ->first();

        $paceWeighted = (float) ($row->pace_weighted ?? 0);
        $paceDistance = (float) ($row->pace_distance ?? 0);
        $pace = $paceDistance > 0
            ? $paceWeighted / $paceDistance
            : ($row->pace_mean !== null ? (float) $row->pace_mean : null);

        return [
            'days' => self::SUMMARY_DAYS,
            'distance_meters' => (float) ($row->distance ?? 0),
            'duration_seconds' => (int) ($row->duration ?? 0),
            'activity_count' => (int) ($row->workout_count ?? 0),
            'elevation_gain_meters' => (float) ($row->elevation ?? 0),
            'relative_effort' => (int) ($row->effort ?? 0),
            'average_pace_seconds_per_km' => $pace,
        ];
    }

    /**
     * Grid exertion 364 hari (kolom = minggu, baris = Senin–Minggu).
     *
     * Intensitas harian diambil dari SUM(relative_effort); bila seluruh periode
     * Relative Effort nol, jatuh ke SUM(training_load). Caption di view memakai
     * `metric` untuk memberi tahu pembaca mana yang dipakai. Sel di luar rentang
     * 364 hari (padding awal/akhir minggu) bernilai null dan digambar kosong.
     *
     * @return array{
     *     weeks: list<list<array{date:?string,value:?float,level:?int}>>,
     *     metric:string,
     *     metric_label:string,
     *     max:float,
     *     active_days:int,
     *     total_score:float,
     *     start:string,
     *     end:string,
     * }
     */
    public function exertionGrid(User $user, Carbon $today): array
    {
        $start = $today->copy()->subDays(self::GRID_DAYS - 1);

        $rows = $user->workouts()
            ->whereBetween('start_date', [$start->copy()->startOfDay(), $today->copy()->endOfDay()])
            // Alias TIDAK boleh memakai reserved word MySQL (`load` bikin
            // syntax error 1064 di MySQL, walau SQLite di test menerimanya).
            ->selectRaw('DATE(start_date) as workout_date, SUM(relative_effort) as effort, SUM(training_load) as training_load_total')
            ->groupBy('workout_date')
            ->get();

        $daily = [];
        $effortTotal = 0.0;
        $loadTotal = 0.0;

        foreach ($rows as $row) {
            $effort = (float) ($row->effort ?? 0);
            $load = (float) ($row->training_load_total ?? 0);
            $daily[$row->workout_date] = ['effort' => $effort, 'load' => $load];
            $effortTotal += $effort;
            $loadTotal += $load;
        }

        $metric = $effortTotal > 0 ? 'relative_effort' : ($loadTotal > 0 ? 'training_load' : 'relative_effort');
        $key = $metric === 'relative_effort' ? 'effort' : 'load';

        $max = 0.0;
        foreach ($daily as $day) {
            $max = max($max, $day[$key]);
        }
        $scaleMax = $max > 0 ? $max : 1.0;

        // Padding agar baris pertama = Senin. Sel sebelum $start / sesudah $today
        // tidak punya tanggal sehingga tidak dihitung sebagai hari data.
        $cursor = $start->copy()->startOfWeek(Carbon::MONDAY);
        $weeks = [];
        $activeDays = 0;
        $totalScore = 0.0;

        while ($cursor->lte($today)) {
            $week = [];

            for ($i = 0; $i < 7; $i++) {
                $inRange = $cursor->gte($start) && $cursor->lte($today);
                $value = $inRange ? ($daily[$cursor->toDateString()][$key] ?? 0.0) : null;

                if ($inRange && $value > 0) {
                    $activeDays++;
                    $totalScore += $value;
                }

                $week[] = [
                    'date' => $inRange ? $cursor->toDateString() : null,
                    'value' => $value,
                    'level' => $value === null ? null : $this->level($value, $scaleMax),
                ];

                $cursor->addDay();
            }

            $weeks[] = $week;
        }

        return [
            'weeks' => $weeks,
            'metric' => $metric,
            'metric_label' => $metric === 'relative_effort' ? 'Relative Effort' : 'Training Load',
            'max' => $max,
            'active_days' => $activeDays,
            'total_score' => round($totalScore, 1),
            'start' => $start->toDateString(),
            'end' => $today->toDateString(),
        ];
    }

    /**
     * Proporsi aktivitas & jarak per tipe (30 hari), dipetakan ke 6 keranjang.
     *
     * @return array{window_days:int,total_count:int,total_distance_meters:float,buckets:list<array{key:string,label:string,count:int,distance_meters:float,count_percent:float,distance_percent:float}>}
     */
    public function disciplineRatio(User $user, Carbon $today): array
    {
        $from = $today->copy()->subDays(self::SUMMARY_DAYS - 1);

        $rows = $user->workouts()
            ->whereBetween('start_date', [$from, $today->copy()->endOfDay()])
            ->selectRaw('type, COUNT(*) as cnt, SUM(distance_meters) as dist')
            ->groupBy('type')
            ->get();

        $labels = [
            'running' => 'Running',
            'trail_running' => 'Trail Running',
            'cycling' => 'Cycling',
            'walking' => 'Walking',
            'hiking' => 'Hiking',
            'lainnya' => 'Lainnya',
        ];

        $buckets = [];
        foreach ($rows as $row) {
            $key = $this->bucketFor((string) $row->type);
            $buckets[$key] ??= ['count' => 0, 'distance_meters' => 0.0];
            $buckets[$key]['count'] += (int) $row->cnt;
            $buckets[$key]['distance_meters'] += (float) ($row->dist ?? 0);
        }

        $totalCount = (int) array_sum(array_column($buckets, 'count'));
        $totalDistance = (float) array_sum(array_column($buckets, 'distance_meters'));

        $result = [];
        foreach ($buckets as $key => $bucket) {
            $result[] = [
                'key' => $key,
                'label' => $labels[$key] ?? ucfirst($key),
                'count' => $bucket['count'],
                'distance_meters' => $bucket['distance_meters'],
                'count_percent' => $totalCount > 0 ? round($bucket['count'] / $totalCount * 100, 1) : 0.0,
                'distance_percent' => $totalDistance > 0 ? round($bucket['distance_meters'] / $totalDistance * 100, 1) : 0.0,
            ];
        }

        usort($result, fn (array $a, array $b): int => $b['count'] <=> $a['count'] ?: $b['distance_meters'] <=> $a['distance_meters']);

        return [
            'window_days' => self::SUMMARY_DAYS,
            'total_count' => $totalCount,
            'total_distance_meters' => $totalDistance,
            'buckets' => $result,
        ];
    }

    /**
     * Radar 5 pilar fisiologis. Skor selalu 0–100 dan dapat dilacak ke satu
     * sumber nyata; null berarti "belum ada data" (tidak digambar sebagai 0).
     *
     * @return list<array{key:string,label:string,score:?int,source:string}>
     */
    public function pillars(User $user, Carbon $today): array
    {
        $from = $today->copy()->subDays(self::SUMMARY_DAYS - 1);

        // Endurance — proporsi hari aktif 30 hari (konsistensi).
        $consistency = $this->consistency->forPeriod($user, $from, $today);
        $endurance = $consistency['days_with_workout'] > 0
            ? $this->clamp((int) round($consistency['consistency_percent']))
            : null;

        // Aerobic base — VO2max terakhir dinormalisasi dari 30 → 0 dan 70 → 100.
        $vo2max = $user->vitalMeasurements()
            ->where('type', 'vo2max')
            ->orderByDesc('date')
            ->value('value');
        $aerobic = $vo2max !== null
            ? $this->clamp((int) round(((float) $vo2max - self::VO2MAX_FLOOR) / (self::VO2MAX_CEILING - self::VO2MAX_FLOOR) * 100))
            : null;

        // Recovery capacity — rata-rata skor recovery 30 hari.
        $recoveryAvg = $user->recoveryScores()
            ->whereDate('date', '>=', $from->toDateString())
            ->avg('score');
        $recovery = $recoveryAvg !== null ? $this->clamp((int) round((float) $recoveryAvg)) : null;

        // Readiness stability — rata-rata dikurangi simpangan baku (variabilitas
        // tinggi menurunkan skor karena pemulihan tidak stabil).
        $readinessScores = $user->readinessScores()
            ->whereDate('date', '>=', $from->toDateString())
            ->pluck('score')
            ->map(fn ($score): float => (float) $score)
            ->all();
        $readiness = null;
        if ($readinessScores !== []) {
            $mean = array_sum($readinessScores) / count($readinessScores);
            $variance = array_sum(array_map(fn (float $v): float => ($v - $mean) ** 2, $readinessScores)) / count($readinessScores);
            $readiness = $this->clamp((int) round($mean - sqrt($variance)));
        }

        // Load balance — jarak ACWR terbaru dari 1.0; makin dekat makin tinggi.
        $ratio = $user->trainingLoads()
            ->whereDate('date', '>=', $from->toDateString())
            ->whereNotNull('acute_chronic_ratio')
            ->orderByDesc('date')
            ->value('acute_chronic_ratio');
        $balance = $ratio !== null
            ? $this->clamp((int) round(100 - abs((float) $ratio - 1.0) * 100))
            : null;

        return [
            ['key' => 'endurance', 'label' => 'Endurance', 'score' => $endurance, 'source' => 'Konsistensi hari aktif 30 hari (TrainingConsistencyService).'],
            ['key' => 'aerobic', 'label' => 'Aerobic Base', 'score' => $aerobic, 'source' => 'VO2max terakhir, dinormalisasi 30→0 / 70→100 ml/kg/min.'],
            ['key' => 'recovery', 'label' => 'Recovery Capacity', 'score' => $recovery, 'source' => 'Rata-rata skor recovery 30 hari dari tabel recovery_scores.'],
            ['key' => 'readiness', 'label' => 'Readiness Stability', 'score' => $readiness, 'source' => 'Rata-rata skor readiness 30 hari dikurangi simpangan bakunya.'],
            ['key' => 'balance', 'label' => 'Load Balance', 'score' => $balance, 'source' => 'Kedekatan ACWR terbaru ke 1.0 (100 − |ACWR−1|×100).'],
        ];
    }

    /**
     * Perbandingan 28 hari terakhir vs 28 hari sebelumnya.
     *
     * Persen null (ditampilkan "--") bila pembanding nol — tidak pernah membagi
     * nol. `direction` = up/down/flat untuk pewarnaan.
     *
     * @return list<array{key:string,label:string,unit:string,current:float,previous:float,percent:?float,direction:string}>
     */
    public function comparison(User $user, Carbon $today): array
    {
        $currentFrom = $today->copy()->subDays(self::COMPARISON_DAYS - 1);
        $previousFrom = $today->copy()->subDays(self::COMPARISON_DAYS * 2 - 1);
        $previousTo = $today->copy()->subDays(self::COMPARISON_DAYS);

        $current = $this->rangeTotals($user, $currentFrom, $today);
        $previous = $this->rangeTotals($user, $previousFrom, $previousTo);

        $definitions = [
            ['key' => 'distance', 'label' => 'Jarak', 'unit' => 'km', 'scale' => 0.001],
            ['key' => 'elevation', 'label' => 'Elevasi', 'unit' => 'm', 'scale' => 1.0],
            ['key' => 'effort', 'label' => 'Relative Effort', 'unit' => '', 'scale' => 1.0],
        ];

        $result = [];
        foreach ($definitions as $definition) {
            $key = $definition['key'];
            $cur = $current[$key] * $definition['scale'];
            $prev = $previous[$key] * $definition['scale'];

            $percent = $prev > 0 ? round(($cur - $prev) / $prev * 100, 1) : null;

            $result[] = [
                'key' => $key,
                'label' => $definition['label'],
                'unit' => $definition['unit'],
                'current' => round($cur, 1),
                'previous' => round($prev, 1),
                'percent' => $percent,
                'direction' => $percent === null ? 'flat' : ($percent > 0 ? 'up' : ($percent < 0 ? 'down' : 'flat')),
            ];
        }

        return $result;
    }

    /**
     * Kartu Personal Record (maksimal 6). `fastest_*` diformat sebagai durasi,
     * `longest_run` sebagai jarak km, sisanya angka mentah.
     *
     * @return list<array{type:string,label:string,value_text:string,unit_text:string,achieved_date:string,source:string}>
     */
    public function personalRecords(User $user): array
    {
        $records = $user->personalRecords()
            ->orderByDesc('achieved_date')
            ->get();

        // Satu kartu per tipe: fastest_* ambil nilai terkecil, longest_run terbesar.
        $best = [];
        foreach ($records as $record) {
            $type = (string) $record->type;
            if (! isset($best[$type])) {
                $best[$type] = $record;

                continue;
            }

            $current = $best[$type];
            $replace = $type === 'longest_run'
                ? (float) $record->value > (float) $current->value
                : (float) $record->value < (float) $current->value;

            if ($replace) {
                $best[$type] = $record;
            }
        }

        $cards = [];
        $usedTypes = [];

        foreach (self::PR_PRIORITY as $type) {
            if (! isset($best[$type])) {
                continue;
            }

            $cards[] = $this->recordCard($best[$type]);
            $usedTypes[$type] = true;
        }

        // Lengkapi hingga 6 kartu dengan label Garmin yang belum dipetakan.
        foreach ($best as $type => $record) {
            if (count($cards) >= self::PR_CARD_LIMIT) {
                break;
            }
            if (isset($usedTypes[$type])) {
                continue;
            }

            $cards[] = $this->recordCard($record);
        }

        return array_slice($cards, 0, self::PR_CARD_LIMIT);
    }

    /** @return array<string,float> */
    private function rangeTotals(User $user, Carbon $from, Carbon $to): array
    {
        $row = $user->workouts()
            ->whereBetween('start_date', [$from, $to->copy()->endOfDay()])
            ->selectRaw('SUM(distance_meters) as distance, SUM(elevation_gain_meters) as elevation, SUM(relative_effort) as effort')
            ->first();

        return [
            'distance' => (float) ($row->distance ?? 0),
            'elevation' => (float) ($row->elevation ?? 0),
            'effort' => (float) ($row->effort ?? 0),
        ];
    }

    /** @return array{type:string,label:string,value_text:string,unit_text:string,achieved_date:string,source:string} */
    private function recordCard(PersonalRecord $record): array
    {
        $type = (string) $record->type;

        return [
            'type' => $type,
            'label' => $record->label(),
            // Satu sumber kebenaran untuk format nilai PR (Model PersonalRecord),
            // supaya halaman ini tidak berbeda dari Running/Training/AI context.
            'value_text' => $record->formattedValue(),
            'unit_text' => '',
            'achieved_date' => $record->achieved_date?->translatedFormat('d M Y') ?? '--',
            // Impor Garmin menyimpan unit sebagai "<activityType>_raw"; baris yang
            // dibuat aplikasi sendiri tidak berakhiran _raw.
            'source' => str_ends_with((string) $record->unit, '_raw') ? 'Garmin' : 'RAGA',
        ];
    }

    private function level(float $value, float $scaleMax): int
    {
        if ($value <= 0.0) {
            return 0;
        }

        return max(1, min(5, (int) ceil($value / $scaleMax * 5)));
    }

    private function clamp(int $score): int
    {
        return max(0, min(100, $score));
    }

    private function bucketFor(string $type): string
    {
        return match (true) {
            $type === 'trail_running' || str_contains($type, 'trail') => 'trail_running',
            $type === 'running' || str_contains($type, 'run') => 'running',
            str_contains($type, 'cycl') || str_contains($type, 'bik') => 'cycling',
            str_contains($type, 'hik') => 'hiking',
            str_contains($type, 'walk') => 'walking',
            default => 'lainnya',
        };
    }
}
