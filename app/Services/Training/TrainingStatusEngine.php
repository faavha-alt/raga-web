<?php

namespace App\Services\Training;

use App\Models\TrainingLoad;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Orchestrates gateway -> calculator -> persistence into the (previously
 * scaffolded but unused) training_loads table. Mirrors RecoveryEngine.
 */
class TrainingStatusEngine
{
    public function __construct(
        private TrainingLoadGateway $gateway,
        private AcuteChronicLoadCalculator $calculator,
    ) {}

    public function calculateAndStoreForDate(User $user, Carbon $date): TrainingLoad
    {
        $dailyLoad = $this->gateway->dailyLoadSeries($user, $date, AcuteChronicLoadCalculator::CHRONIC_WINDOW_DAYS);
        $result = $this->calculator->calculate($dailyLoad, $date);

        $weekStart = $date->copy()->startOfWeek();
        $weekEnd = $date->copy()->endOfWeek();

        return $this->upsertByDate($user, $date, [
            'acute_load' => $result['acute_load'],
            'chronic_load' => $result['chronic_load'],
            'acute_chronic_ratio' => $result['acute_chronic_ratio'],
            'monotony' => $result['monotony'],
            'risk_level' => $result['risk_level'],
            'weekly_distance_meters' => $this->gateway->weeklyDistanceMeters($user, $weekStart, $weekEnd),
            'weekly_duration_minutes' => $this->gateway->weeklyDurationMinutes($user, $weekStart, $weekEnd),
            'training_frequency' => $this->gateway->trainingFrequency($user, $weekStart, $weekEnd),
        ]);
    }

    public function calculateForDateRange(User $user, int $days): void
    {
        $today = Carbon::today();

        for ($i = 0; $i < $days; $i++) {
            $this->calculateAndStoreForDate($user, $today->copy()->subDays($i));
        }
    }

    /**
     * whereDate()-based find-or-create rather than TrainingLoad::updateOrCreate()'s
     * exact-string WHERE match — the 'date' cast's raw stored representation
     * isn't guaranteed byte-identical across database drivers (see
     * RecoveryEngine::upsertByDate for the same lesson learned there).
     */
    private function upsertByDate(User $user, Carbon $date, array $attributes): TrainingLoad
    {
        $existing = TrainingLoad::query()
            ->where('user_id', $user->id)
            ->whereDate('date', $date->toDateString())
            ->first();

        if ($existing) {
            $existing->update($attributes);

            return $existing;
        }

        return TrainingLoad::create($attributes + ['user_id' => $user->id, 'date' => $date->toDateString()]);
    }
}
