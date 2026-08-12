<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['workout_id', 'timestamp', 'heart_rate', 'pace_seconds_per_km', 'altitude_meters', 'cadence'])]
class WorkoutSample extends Model
{
    protected function casts(): array
    {
        return ['timestamp' => 'datetime'];
    }

    public function workout(): BelongsTo
    {
        return $this->belongsTo(Workout::class);
    }
}
