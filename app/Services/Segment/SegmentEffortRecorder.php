<?php

namespace App\Services\Segment;

use App\Models\Segment;
use App\Models\SegmentEffort;
use App\Models\Workout;
use App\Services\Trail\TrailMovingTimeCalculator;
use Illuminate\Support\Carbon;

/**
 * Menyimpan hasil pencocokan menjadi SegmentEffort.
 *
 * Aturan:
 *  - maksimal SATU effort per (segment_id, workout_id) — index unik di DB;
 *    bila workout yang sama sudah punya effort, hanya yang TERCEPAT yang
 *    dipertahankan;
 *  - `is_personal_best` diberikan ke effort tercepat MILIK SETIAP PENGGUNA
 *    di segment tersebut;
 *  - `segments.effort_count` disinkronkan untuk pembukuan internal SAJA.
 *    Kolom ini menghitung SEMUA effort (termasuk dari aktivitas private orang
 *    lain), jadi nilainya TIDAK BOLEH dirender ke pengguna; tampilan memakai
 *    SegmentLeaderboardService::visibleEffortCount() / `visible_effort_count`.
 *
 * `moving_seconds` dihitung dengan TrailMovingTimeCalculator (logika yang
 * sama dengan halaman trail) dari rentang sampel yang cocok.
 */
class SegmentEffortRecorder
{
    public function __construct(private TrailMovingTimeCalculator $movingTime) {}

    /**
     * @param  list<array{lat: float, lng: float, altitude: ?float, timestamp: int, heart_rate: ?float, pace: ?float}>  $points
     * @param  array{start_index: int, end_index: int, elapsed_seconds: float}  $match
     */
    public function record(Segment $segment, Workout $workout, array $points, array $match): SegmentEffort
    {
        $slice = array_slice($points, $match['start_index'], $match['end_index'] - $match['start_index'] + 1);
        $elapsed = (float) $match['elapsed_seconds'];

        $attributes = [
            'user_id' => $workout->user_id,
            'started_at' => Carbon::createFromTimestamp((int) round($match['start_timestamp'])),
            'elapsed_seconds' => $elapsed,
            'moving_seconds' => $this->movingSeconds($slice),
            'average_heart_rate' => $this->averageHeartRate($slice),
            'average_pace_seconds_per_km' => $segment->distance_meters > 0
                ? $elapsed / ($segment->distance_meters / 1000)
                : null,
        ];

        $existing = SegmentEffort::query()
            ->where('segment_id', $segment->id)
            ->where('workout_id', $workout->id)
            ->first();

        if ($existing !== null) {
            if ($elapsed < (float) $existing->elapsed_seconds) {
                $existing->update($attributes);
            }

            return $existing->refresh();
        }

        $effort = SegmentEffort::create($attributes + [
            'segment_id' => $segment->id,
            'workout_id' => $workout->id,
        ]);

        $this->syncPersonalBests($segment);

        return $effort;
    }

    /**
     * Hitung ulang PB per pengguna dan jumlah effort segment.
     */
    public function syncPersonalBests(Segment $segment): void
    {
        SegmentEffort::query()
            ->where('segment_id', $segment->id)
            ->update(['is_personal_best' => false]);

        $bestPerUser = SegmentEffort::query()
            ->where('segment_id', $segment->id)
            ->selectRaw('user_id, MIN(elapsed_seconds) as best_elapsed')
            ->groupBy('user_id')
            ->get();

        foreach ($bestPerUser as $row) {
            SegmentEffort::query()
                ->where('segment_id', $segment->id)
                ->where('user_id', $row->user_id)
                ->orderBy('elapsed_seconds')
                ->orderBy('id')
                ->limit(1)
                ->update(['is_personal_best' => true]);
        }

        $segment->update([
            'effort_count' => SegmentEffort::query()->where('segment_id', $segment->id)->count(),
        ]);
    }

    /**
     * @param  list<array{timestamp: int}>  $slice
     */
    private function movingSeconds(array $slice): ?int
    {
        if (count($slice) < 2) {
            return null;
        }

        return $this->movingTime->movingSeconds(array_map(
            fn ($point) => ['timestamp' => Carbon::createFromTimestamp($point['timestamp'])],
            $slice,
        ));
    }

    /**
     * @param  list<array{heart_rate: ?float}>  $slice
     */
    private function averageHeartRate(array $slice): ?float
    {
        $values = array_values(array_filter(
            array_column($slice, 'heart_rate'),
            fn ($value) => $value !== null,
        ));

        if ($values === []) {
            return null;
        }

        return round(array_sum($values) / count($values), 1);
    }
}
