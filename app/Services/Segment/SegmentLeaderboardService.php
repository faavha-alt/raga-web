<?php

namespace App\Services\Segment;

use App\Models\Segment;
use App\Models\SegmentEffort;
use App\Models\User;
use App\Models\Workout;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Leaderboard sebuah segment.
 *
 * PRIVASI (keputusan): effort TETAP disimpan untuk semua aktivitas, apa pun
 * visibilitasnya, karena effort pada dasarnya adalah data milik pemilik
 * aktivitas. Penyaringan dilakukan saat BACA dengan `Workout::visibleTo()`;
 * dengan begitu aktivitas `private` orang lain tidak pernah muncul di
 * leaderboard pengguna lain, sementara pemiliknya tetap melihat usahanya
 * sendiri. Filter visibilitas ini juga dipakai di subquery "tidak ada yang
 * lebih cepat", supaya effort private yang cepat tidak menutupi effort
 * publik yang lebih lambat milik pengguna yang sama.
 *
 * Satu baris = satu atlet (effort tercepatnya), diurutkan tercepat dulu.
 */
class SegmentLeaderboardService
{
    public const PER_PAGE = 25;

    public function forSegment(Segment $segment, ?User $viewer, int $perPage = self::PER_PAGE): LengthAwarePaginator
    {
        return SegmentEffort::query()
            ->with(['user', 'workout'])
            ->where('segment_id', $segment->id)
            ->whereIn('workout_id', Workout::visibleTo($viewer)->select('id'))
            ->whereNotExists(fn ($query) => $this->noFasterVisibleEffort($query, $viewer))
            ->orderBy('elapsed_seconds')
            ->orderBy('id')
            ->paginate($perPage, ['*'], 'leaderboard_page')
            ->withQueryString();
    }

    /**
     * Jumlah effort yang BOLEH dilihat $viewer.
     *
     * Kolom denormalisasi `segments.effort_count` sengaja TIDAK dipakai untuk
     * tampilan: kolom itu menghitung SEMUA effort, termasuk yang berasal dari
     * aktivitas private orang lain. Selisih angka itu bisa dipakai untuk
     * menyimpulkan apakah atlet tertentu melewati sebuah lokasi (inference
     * attack), jadi setiap angka yang dirender harus lewat filter visibilitas.
     */
    public function visibleEffortCount(Segment $segment, ?User $viewer): int
    {
        return SegmentEffort::query()
            ->where('segment_id', $segment->id)
            ->whereIn('workout_id', Workout::visibleTo($viewer)->select('id'))
            ->count();
    }

    /**
     * Semua usaha milik $viewer pada segment ini (bukan hanya yang terbaik).
     *
     * @return Collection<int, SegmentEffort>
     */
    public function myEfforts(Segment $segment, ?User $viewer): Collection
    {
        if ($viewer === null) {
            return new Collection;
        }

        return SegmentEffort::query()
            ->with('workout')
            ->where('segment_id', $segment->id)
            ->where('user_id', $viewer->id)
            ->orderBy('elapsed_seconds')
            ->orderBy('id')
            ->get();
    }

    /**
     * Subquery korelasi: tidak boleh ada effort LAIN yang lebih cepat untuk
     * pengguna yang sama di antara aktivitas yang boleh dilihat $viewer.
     */
    private function noFasterVisibleEffort($query, ?User $viewer): void
    {
        $query->select(DB::raw(1))
            ->from('segment_efforts as faster')
            ->whereColumn('faster.segment_id', 'segment_efforts.segment_id')
            ->whereColumn('faster.user_id', 'segment_efforts.user_id')
            ->whereIn('faster.workout_id', Workout::visibleTo($viewer)->select('id'))
            ->where(function ($query): void {
                $query->whereColumn('faster.elapsed_seconds', '<', 'segment_efforts.elapsed_seconds')
                    ->orWhere(function ($query): void {
                        $query->whereColumn('faster.elapsed_seconds', '=', 'segment_efforts.elapsed_seconds')
                            ->whereColumn('faster.id', '<', 'segment_efforts.id');
                    });
            });
    }
}
