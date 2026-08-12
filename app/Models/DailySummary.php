<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'date', 'sleep_session_id', 'activity_summary_id', 'health_score_id', 'recovery_score_id'])]
class DailySummary extends Model
{
    protected function casts(): array
    {
        return ['date' => 'date'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sleepSession(): BelongsTo
    {
        return $this->belongsTo(SleepSession::class);
    }

    public function activitySummary(): BelongsTo
    {
        return $this->belongsTo(ActivitySummary::class);
    }

    public function healthScore(): BelongsTo
    {
        return $this->belongsTo(HealthScore::class);
    }

    public function recoveryScore(): BelongsTo
    {
        return $this->belongsTo(RecoveryScore::class);
    }
}
