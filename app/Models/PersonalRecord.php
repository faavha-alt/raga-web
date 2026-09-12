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

    /**
     * Nilai PR siap tampil.
     *
     * `fastest_*` (1K/1mi/5K/10K/HM/marathon) dikirim Garmin dalam **detik**,
     * sedangkan PR jarak seperti `longest_run` dikirim dalam **meter** — sama
     * seperti PR jarak lain di feed yang sama (mis. road_biking 113.979 m =
     * 114 km). Sebelumnya `longest_run` ikut diformat sebagai durasi, sehingga
     * PR 10,25 km tampil sebagai "2:50:46"; sekarang konsisten sebagai jarak.
     */
    public function formattedValue(): string
    {
        if (str_starts_with($this->type, 'fastest_')) {
            $seconds = (int) $this->value;
            $h = intdiv($seconds, 3600);
            $m = intdiv($seconds % 3600, 60);
            $s = $seconds % 60;

            return $h > 0 ? sprintf('%d:%02d:%02d', $h, $m, $s) : sprintf('%d:%02d', $m, $s);
        }

        if ($this->type === 'longest_run') {
            return number_format($this->value / 1000, 2).' km';
        }

        return number_format($this->value, 1);
    }
}
