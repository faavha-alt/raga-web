<?php

namespace App\Services\Segment;

use App\Models\Segment;
use App\Models\SegmentEffort;
use App\Models\User;
use App\Models\Workout;
use Illuminate\Contracts\Database\Eloquent\Builder;

/**
 * Query untuk daftar segment di /segments.
 *
 * Kolom & arah pengurutan di-allow-list (tidak pernah menginterpolasi input
 * pengguna ke SQL). Filter `search` mencocokkan nama ATAU tipe aktivitas.
 *
 * Jumlah effort yang dirender TIDAK diambil dari kolom denormalisasi
 * `segments.effort_count` (kolom itu juga menghitung effort dari aktivitas
 * private orang lain). Sebagai gantinya query ini menambahkan
 * `visible_effort_count` yang sudah difilter `Workout::visibleTo($user)`,
 * termasuk untuk pengurutan "Jumlah Effort".
 */
class SegmentQueryService
{
    private const SORTABLE = [
        'name' => 'name',
        'distance' => 'distance_meters',
        'elevation' => 'elevation_gain_meters',
        'created' => 'created_at',
    ];

    /** Filtered + sorted, siap di-paginate. */
    public function browseFor(User $user, array $filters): Builder
    {
        $query = Segment::query()
            ->where(function (Builder $query) use ($user): void {
                $query->where('is_public', true)->orWhere('user_id', $user->id);
            })
            ->withCount(['efforts as visible_effort_count' => function (Builder $query) use ($user): void {
                $query->whereIn('workout_id', Workout::visibleTo($user)->select('id'));
            }]);

        if (! empty($filters['search'])) {
            $search = $this->escapeLike($filters['search']);
            $pattern = '%'.$search.'%';

            $query->where(function (Builder $query) use ($pattern): void {
                $query->whereRaw("name like ? escape '!'", [$pattern])
                    ->orWhereRaw("activity_type like ? escape '!'", [$pattern]);
            });
        }

        if (! empty($filters['activity_type'])) {
            $query->where('activity_type', $filters['activity_type']);
        }

        $sortKey = $filters['sort'] ?? 'created';
        $direction = ($filters['direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        if ($sortKey === 'efforts') {
            $query->orderBy($this->visibleEffortCountSubquery($user), $direction);
        } else {
            $column = self::SORTABLE[$sortKey] ?? self::SORTABLE['created'];
            $query->orderBy($column, $direction);
        }

        return $query;
    }

    /** @return list<string> */
    public function activityTypes(?User $viewer): array
    {
        return Segment::query()
            ->where(function (Builder $query) use ($viewer): void {
                $query->where('is_public', true);

                if ($viewer !== null) {
                    $query->orWhere('user_id', $viewer->id);
                }
            })
            ->select('activity_type')
            ->distinct()
            ->orderBy('activity_type')
            ->pluck('activity_type')
            ->all();
    }

    /** Subquery korelasi: jumlah effort visible per baris `segments`. */
    private function visibleEffortCountSubquery(User $user): Builder
    {
        return SegmentEffort::query()
            ->selectRaw('count(*)')
            ->whereColumn('segment_id', 'segments.id')
            ->whereIn('workout_id', Workout::visibleTo($user)->select('id'));
    }

    /**
     * Netralkan wildcard `%` dan `_` pada kata kunci pencarian agar filter
     * mencocokkan teks secara literal (bukan pola LIKE yang terlalu luas).
     * Karakter escape `!` ikut di-escape lebih dulu.
     */
    private function escapeLike(string $search): string
    {
        return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search);
    }
}
