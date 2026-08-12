<?php

namespace App\Models;

use App\Support\ScoreCategory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id', 'date', 'score', 'hrv_contribution', 'resting_heart_rate_contribution',
    'sleep_contribution', 'training_load_contribution', 'calculated_at',
])]
class RecoveryScore extends Model
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
        return ScoreCategory::fromScore($this->score);
    }
}
