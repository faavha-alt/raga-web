<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'workout_id', 'type', 'value', 'unit', 'achieved_date'])]
class PersonalRecord extends Model
{
    protected function casts(): array
    {
        return ['achieved_date' => 'date'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function workout(): BelongsTo
    {
        return $this->belongsTo(Workout::class);
    }

    public function label(): string
    {
        return match ($this->type) {
            'fastest_1k' => 'Fastest 1K',
            'fastest_1_mile' => 'Fastest 1 Mile',
            'fastest_5k' => 'Fastest 5K',
            'fastest_10k' => 'Fastest 10K',
            'fastest_half_marathon' => 'Fastest Half Marathon',
            'fastest_marathon' => 'Fastest Marathon',
            'longest_run' => 'Longest Run',
            default => 'Garmin PR #'.str_replace('garmin_pr_type_', '', $this->type),
        };
    }

    public function formattedValue(): string
    {
        if (str_starts_with($this->type, 'fastest_') || $this->type === 'longest_run') {
            $seconds = (int) $this->value;
            $h = intdiv($seconds, 3600);
            $m = intdiv($seconds % 3600, 60);
            $s = $seconds % 60;

            return $h > 0 ? sprintf('%d:%02d:%02d', $h, $m, $s) : sprintf('%d:%02d', $m, $s);
        }

        return number_format($this->value, 1);
    }
}
