<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'timestamp', 'sdnn_milliseconds', 'source'])]
class HrvSample extends Model
{
    protected $table = 'hrv_samples';

    protected function casts(): array
    {
        return ['timestamp' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
