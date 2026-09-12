<?php

namespace App\Models;

use App\Support\ActivityVisibility;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id', 'type', 'name', 'description', 'start_date', 'end_date', 'distance_meters',
    'active_calories', 'average_heart_rate', 'max_heart_rate',
    'average_pace_seconds_per_km', 'elevation_gain_meters', 'elevation_loss_meters',
    'training_effect_aerobic', 'training_effect_anaerobic', 'training_effect_label',
    'training_load', 'relative_effort', 'source', 'visibility', 'location_name',
])]
class Workout extends Model
{
    /**
     * Cerminan default kolom `visibility` di database.
     *
     * Tanpa ini, model yang baru dibuat (mis. hasil impor Garmin) belum punya
     * atribut visibility di memori sampai di-refresh, sehingga pengecekan
     * privasi bisa membaca null.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'visibility' => 'private',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'datetime',
            'end_date' => 'datetime',
            'visibility' => ActivityVisibility::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function samples(): HasMany
    {
        return $this->hasMany(WorkoutSample::class);
    }

    public function laps(): HasMany
    {
        return $this->hasMany(WorkoutLap::class)->orderBy('lap_index');
    }

    public function kudos(): HasMany
    {
        return $this->hasMany(Kudos::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class)->oldest();
    }

    public function segmentEfforts(): HasMany
    {
        return $this->hasMany(SegmentEffort::class);
    }

    public function durationSeconds(): int
    {
        return abs($this->end_date->diffInSeconds($this->start_date));
    }

    /**
     * Gerbang privasi tingkat query.
     *
     * Menyaring aktivitas yang boleh dilihat $viewer dalam SATU query (memakai
     * subquery, bukan pluck lalu whereIn) supaya feed tetap efisien. Aturannya:
     *  - publik: semua orang, termasuk pengunjung tanpa login;
     *  - followers: pemilik dan orang yang mengikutinya;
     *  - private: hanya pemilik.
     */
    #[Scope]
    protected function visibleTo(Builder $query, ?User $viewer): Builder
    {
        $public = ActivityVisibility::Public->value;
        $followers = ActivityVisibility::Followers->value;

        if ($viewer === null) {
            return $query->where('visibility', $public);
        }

        return $query->where(function (Builder $query) use ($viewer, $public, $followers): void {
            $query->where('visibility', $public)
                ->orWhere('user_id', $viewer->id)
                ->orWhere(function (Builder $query) use ($viewer, $followers): void {
                    $query->where('visibility', $followers)
                        ->whereIn('user_id', Follow::query()
                            ->where('follower_id', $viewer->id)
                            ->select('following_id'));
                });
        });
    }

    /**
     * Apakah aktivitas ini boleh dilihat oleh $viewer.
     */
    public function isVisibleTo(?User $viewer): bool
    {
        $visibility = $this->visibility instanceof ActivityVisibility
            ? $this->visibility
            : ActivityVisibility::from((string) $this->visibility);

        if ($viewer !== null && $viewer->id === $this->user_id) {
            return true;
        }

        return $visibility->isVisibleTo(
            isOwner: false,
            viewerFollowsOwner: $viewer !== null && $viewer->isFollowing($this->user),
        );
    }
}
