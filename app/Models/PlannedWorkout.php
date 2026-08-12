<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'training_day_id', 'type', 'duration_minutes', 'distance_meters',
    'target_pace_seconds_per_km', 'target_heart_rate_zone', 'intensity',
    'warm_up', 'main_set', 'cool_down', 'notes', 'status',
    'adapted_from_description', 'adapted_reason',
])]
class PlannedWorkout extends Model
{
    public function day(): BelongsTo
    {
        return $this->belongsTo(TrainingDay::class, 'training_day_id');
    }

    public function completedWorkout(): HasOne
    {
        return $this->hasOne(CompletedWorkout::class);
    }
}
