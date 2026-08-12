<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'timestamp', 'bpm', 'is_resting', 'source'])]
class HeartRateSample extends Model
{
    protected function casts(): array
    {
        return [
            'timestamp' => 'datetime',
            'is_resting' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
