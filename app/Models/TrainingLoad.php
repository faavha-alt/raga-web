<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id', 'date', 'acute_load', 'chronic_load', 'acute_chronic_ratio',
    'weekly_distance_meters', 'weekly_duration_minutes', 'training_frequency',
    'monotony', 'risk_level',
])]
class TrainingLoad extends Model
{
    protected function casts(): array
    {
        return ['date' => 'date'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
