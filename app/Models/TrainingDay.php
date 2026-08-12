<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['training_week_id', 'date'])]
class TrainingDay extends Model
{
    protected function casts(): array
    {
        return ['date' => 'date'];
    }

    public function week(): BelongsTo
    {
        return $this->belongsTo(TrainingWeek::class, 'training_week_id');
    }

    public function plannedWorkouts(): HasMany
    {
        return $this->hasMany(PlannedWorkout::class);
    }
}
