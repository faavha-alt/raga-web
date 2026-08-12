<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id', 'bedtime', 'wake_time', 'rem_minutes', 'deep_minutes',
    'core_minutes', 'awake_minutes', 'sleep_score', 'source',
])]
class SleepSession extends Model
{
    protected function casts(): array
    {
        return [
            'bedtime' => 'datetime',
            'wake_time' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function totalDurationMinutes(): float
    {
        return $this->bedtime->diffInMinutes($this->wake_time);
    }
}
