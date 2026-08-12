<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['planned_workout_id', 'workout_id', 'compliance_score'])]
class CompletedWorkout extends Model
{
    public function plannedWorkout(): BelongsTo
    {
        return $this->belongsTo(PlannedWorkout::class);
    }

    public function workout(): BelongsTo
    {
        return $this->belongsTo(Workout::class);
    }
}
