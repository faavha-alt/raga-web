<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'segment_id', 'user_id', 'workout_id', 'started_at', 'elapsed_seconds',
    'moving_seconds', 'average_heart_rate', 'average_pace_seconds_per_km',
    'is_personal_best',
])]
class SegmentEffort extends Model
{
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'elapsed_seconds' => 'float',
            'moving_seconds' => 'float',
            'average_heart_rate' => 'float',
            'average_pace_seconds_per_km' => 'float',
            'is_personal_best' => 'boolean',
        ];
    }

    public function segment(): BelongsTo
    {
        return $this->belongsTo(Segment::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function workout(): BelongsTo
    {
        return $this->belongsTo(Workout::class);
    }
}
