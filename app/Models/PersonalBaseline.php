<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'metric_type', 'window_days', 'mean_value', 'standard_deviation', 'sample_count', 'calculated_at'])]
class PersonalBaseline extends Model
{
    protected function casts(): array
    {
        return ['calculated_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
