<?php

namespace App\Models;

use App\Support\ScoreCategory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id', 'date', 'overall_score', 'sleep_component', 'recovery_component',
    'hrv_component', 'resting_heart_rate_component', 'activity_component',
    'training_component', 'stress_component', 'sleep_consistency_component', 'calculated_at',
])]
class HealthScore extends Model
{
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'calculated_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): ScoreCategory
    {
        return ScoreCategory::fromScore($this->overall_score);
    }
}
