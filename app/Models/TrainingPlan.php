<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'training_goal_id', 'name', 'start_date', 'target_date', 'status'])]
class TrainingPlan extends Model
{
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'target_date' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function goal(): BelongsTo
    {
        return $this->belongsTo(TrainingGoal::class, 'training_goal_id');
    }

    public function weeks(): HasMany
    {
        return $this->hasMany(TrainingWeek::class);
    }

    /** Total planned workouts across all weeks/days (requires relations loaded). */
    public function totalPlannedWorkouts(): int
    {
        return $this->weeks->flatMap->days->sum(fn ($day) => $day->plannedWorkouts->count());
    }

    /** Number of planned workouts marked as completed (requires completedWorkout loaded). */
    public function completedPlannedWorkouts(): int
    {
        return $this->weeks->flatMap->days->flatMap->plannedWorkouts
            ->filter(fn ($pw) => $pw->completedWorkout !== null)
            ->count();
    }
}
