<?php

namespace App\Services\Social;

use App\Models\Follow;
use App\Models\User;
use App\Models\Workout;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

/**
 * Query terpusat untuk semua daftar aktivitas di permukaan sosial.
 *
 * Setiap query WAJIB melewati scope Workout::visibleTo() supaya aktivitas
 * private/followers tidak pernah bocor, dan setiap query memuat agregat
 * kudos/komentar sekaligus agar tidak ada N+1 di feed.
 */
class ActivityFeedService
{
    private const WITH_COUNTS = ['kudos', 'comments'];

    /**
     * Feed: aktivitas milik sendiri + orang yang diikuti, terbaru dahulu.
     */
    public function feedFor(User $viewer, int $perPage = 20): LengthAwarePaginator
    {
        $followingIds = Follow::query()
            ->where('follower_id', $viewer->id)
            ->select('following_id');

        return $this->baseQuery($viewer)
            ->where(function (Builder $query) use ($viewer, $followingIds): void {
                $query->where('workouts.user_id', $viewer->id)
                    ->orWhereIn('workouts.user_id', $followingIds);
            })
            ->orderByDesc('workouts.start_date')
            ->paginate($perPage);
    }

    /**
     * Aktivitas seorang atlet yang boleh dilihat $viewer (profil atlet).
     */
    public function forAthlete(User $athlete, User $viewer, int $perPage = 10): LengthAwarePaginator
    {
        return $this->baseQuery($viewer)
            ->where('workouts.user_id', $athlete->id)
            ->orderByDesc('workouts.start_date')
            ->paginate($perPage);
    }

    /**
     * Cuplikan aktivitas publik terbaru untuk halaman jelajah.
     *
     * @return Collection<int, Workout>
     */
    public function publicPreview(User $viewer, int $limit = 6): Collection
    {
        return $this->baseQuery($viewer)
            ->where('workouts.visibility', 'public')
            ->where('workouts.user_id', '!=', $viewer->id)
            ->orderByDesc('workouts.start_date')
            ->limit($limit)
            ->get();
    }

    /**
     * Dasar semua daftar: gerbang privasi + eager load penulis + agregat sosial.
     */
    private function baseQuery(?User $viewer): Builder
    {
        return Workout::query()
            ->visibleTo($viewer)
            ->with('user')
            ->withCount(self::WITH_COUNTS)
            ->withExists([
                'kudos as viewer_has_kudos' => fn (Builder $query) => $query->where('user_id', $viewer?->id ?? 0),
            ]);
    }
}
