<?php

namespace App\Services\Training;

use App\Models\TrainingGoal;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Computes how far a user has progressed toward each TrainingGoal, using the
 * same workout data the training pages render from. Each goal type has its
 * own "current" definition; custom goals have no measurable auto-progress.
 */
class TrainingGoalService
{
    private const TYPE_META = [
        'weekly_distance' => ['label' => 'Jarak Mingguan', 'unit' => 'km'],
        'monthly_distance' => ['label' => 'Jarak Bulanan', 'unit' => 'km'],
        'weekly_frequency' => ['label' => 'Frekuensi Mingguan', 'unit' => 'latihan'],
        'race' => ['label' => 'Target Lomba', 'unit' => 'km'],
        'custom' => ['label' => 'Target Kustom', 'unit' => null],
    ];

    /** @return list<array{type: string, label: string}> */
    public function types(): array
    {
        return collect(self::TYPE_META)->map(
            fn ($meta, $type) => ['type' => $type, 'label' => $meta['label']],
        )->values()->all();
    }

    /**
     * @return array{type: string, label: string, unit: ?string, current: ?float, target: ?float, percent: ?int, current_text: string, target_text: string}
     */
    public function progressFor(User $user, TrainingGoal $goal): array
    {
        $meta = self::TYPE_META[$goal->type] ?? ['label' => $goal->type, 'unit' => null];
        $target = $goal->target_value !== null ? (float) $goal->target_value : null;

        $current = match ($goal->type) {
            'weekly_distance' => $this->distanceBetween($user, Carbon::now()->startOfWeek(), Carbon::now()),
            'monthly_distance' => $this->distanceBetween($user, Carbon::now()->startOfMonth(), Carbon::now()),
            'weekly_frequency' => $this->workoutCountBetween($user, Carbon::now()->startOfWeek(), Carbon::now()),
            'race' => $this->longestRunKm($user, 90),
            default => null,
        };

        $percent = ($current !== null && $target !== null && $target > 0)
            ? (int) round(min(100, ($current / $target) * 100))
            : null;

        return [
            'type' => $goal->type,
            'label' => $meta['label'],
            'unit' => $meta['unit'],
            'current' => $current,
            'target' => $target,
            'percent' => $percent,
            'current_text' => $this->formatValue($current, $meta['unit']),
            'target_text' => $this->formatValue($target, $meta['unit']),
        ];
    }

    private function distanceBetween(User $user, Carbon $from, Carbon $to): ?float
    {
        $meters = $user->workouts()
            ->where('start_date', '>=', $from->startOfDay())
            ->where('start_date', '<', $to->copy()->addDay()->startOfDay())
            ->sum('distance_meters');

        return $meters > 0 ? $meters / 1000 : 0.0;
    }

    private function workoutCountBetween(User $user, Carbon $from, Carbon $to): int
    {
        return $user->workouts()
            ->where('start_date', '>=', $from->startOfDay())
            ->where('start_date', '<', $to->copy()->addDay()->startOfDay())
            ->count();
    }

    private function longestRunKm(User $user, int $days): ?float
    {
        $since = Carbon::now()->subDays($days);

        $longest = $user->workouts()
            ->where('start_date', '>=', $since)
            ->where(function ($q) {
                $q->where('type', 'like', '%run%')->orWhere('type', 'like', '%trail%');
            })
            ->orderByDesc('distance_meters')
            ->first();

        return $longest?->distance_meters ? $longest->distance_meters / 1000 : null;
    }

    private function formatValue(?float $value, ?string $unit): string
    {
        if ($value === null) {
            return '—';
        }

        $formatted = $unit === 'km'
            ? number_format($value, $unit === 'km' ? 1 : 0)
            : number_format($value, 0);

        return $unit ? $formatted.' '.$unit : $formatted;
    }
}
