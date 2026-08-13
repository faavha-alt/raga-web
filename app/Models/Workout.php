<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id', 'type', 'name', 'start_date', 'end_date', 'distance_meters',
    'active_calories', 'average_heart_rate', 'max_heart_rate',
    'average_pace_seconds_per_km', 'elevation_gain_meters', 'elevation_loss_meters',
    'training_effect_aerobic', 'training_effect_anaerobic', 'training_effect_label',
    'training_load', 'source',
])]
class Workout extends Model
{
    protected function casts(): array
    {
        return [
            'start_date' => 'datetime',
            'end_date' => 'datetime',
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

    public function durationSeconds(): int
    {
        return abs($this->end_date->diffInSeconds($this->start_date));
    }
}
