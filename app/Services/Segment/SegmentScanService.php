<?php

namespace App\Services\Segment;

use App\Models\Segment;
use App\Models\Workout;
use App\Models\WorkoutSample;
use Illuminate\Database\Eloquent\Collection;

/**
 * Menjalankan pencocokan satu segment terhadap seluruh aktivitas kandidat.
 *
 * Optimasi (prefilter bounding box): pencarian sampel per sampel itu mahal,
 * jadi sebelum menyentuh sampel lengkap kita cari dulu ID workout yang
 * PUNYA MINIMAL SATU sampel di dalam kotak pembatas (bounding box) yang
 * mencakup titik awal & akhir segment + radius. Query `distinct` pada
 * `workout_samples(latitude, longitude)` ini membuang sebagian besar
 * aktivitas tanpa memuat seluruh sampelnya; baru kandidat itu yang dimuat
 * penuh dan dijalankan lewat SegmentMatcher.
 *
 * Tidak ada queue worker di produksi, jadi pemindaian dijalankan sinkron dan
 * dibatasi MAX_CANDIDATE_WORKOUTS agar tetap aman.
 */
class SegmentScanService
{
    public const MAX_CANDIDATE_WORKOUTS = 500;

    private const METERS_PER_DEGREE_LATITUDE = 111320.0;

    public function __construct(
        private SegmentMatcher $matcher,
        private SegmentTrackBuilder $trackBuilder,
        private SegmentEffortRecorder $recorder,
    ) {}

    /**
     * Pindai ulang segment; hasil effort selalu dibatasi satu per workout
     * (yang tercepat) dan effort_count disinkronkan di akhir.
     *
     * @return int jumlah effort yang dicatat/diperbarui
     */
    public function scan(Segment $segment): int
    {
        $matched = 0;

        foreach ($this->candidateWorkouts($segment) as $workout) {
            if (! SegmentMatcher::typesCompatible($segment->activity_type, $workout->type)) {
                continue;
            }

            $points = $this->trackBuilder->pointsForWorkout($workout);

            $match = $this->matcher->match([
                'start_lat' => $segment->start_lat,
                'start_lng' => $segment->start_lng,
                'end_lat' => $segment->end_lat,
                'end_lng' => $segment->end_lng,
            ], $points);

            if ($match === null) {
                continue;
            }

            $this->recorder->record($segment, $workout, $points, $match);
            $matched++;
        }

        $this->recorder->syncPersonalBests($segment);

        return $matched;
    }

    /**
     * @return Collection<int, Workout>
     */
    private function candidateWorkouts(Segment $segment)
    {
        $latDelta = SegmentMatcher::DEFAULT_RADIUS_METERS / self::METERS_PER_DEGREE_LATITUDE;
        $centerLat = ($segment->start_lat + $segment->end_lat) / 2;
        $lngDelta = SegmentMatcher::DEFAULT_RADIUS_METERS
            / (self::METERS_PER_DEGREE_LATITUDE * max(0.01, cos(deg2rad($centerLat))));

        $minLat = min($segment->start_lat, $segment->end_lat) - $latDelta;
        $maxLat = max($segment->start_lat, $segment->end_lat) + $latDelta;
        $minLng = min($segment->start_lng, $segment->end_lng) - $lngDelta;
        $maxLng = max($segment->start_lng, $segment->end_lng) + $lngDelta;

        $candidateIds = WorkoutSample::query()
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->whereBetween('latitude', [$minLat, $maxLat])
            ->whereBetween('longitude', [$minLng, $maxLng])
            ->distinct()
            ->limit(self::MAX_CANDIDATE_WORKOUTS)
            ->pluck('workout_id');

        if ($candidateIds->isEmpty()) {
            return new Collection;
        }

        return Workout::query()
            ->whereIn('id', $candidateIds)
            ->get(['id', 'user_id', 'type', 'start_date', 'visibility']);
    }
}
