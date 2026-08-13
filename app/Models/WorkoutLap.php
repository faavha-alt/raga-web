<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'workout_id', 'lap_index', 'start_time', 'distance_meters', 'duration_seconds',
    'elevation_gain_meters', 'elevation_loss_meters', 'average_heart_rate',
    'max_heart_rate', 'average_pace_seconds_per_km', 'calories',
])]
class WorkoutLap extends Model
{
    protected function casts(): array
    {
        return ['start_time' => 'datetime'];
    }

    public function workout(): BelongsTo
    {
        return $this->belongsTo(Workout::class);
    }
}
