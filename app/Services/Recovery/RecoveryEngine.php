<?php

namespace App\Services\Recovery;

use App\Models\ReadinessScore;
use App\Models\RecoveryScore;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Orchestrates gateway -> calculators -> persistence. This is what
 * controllers/commands call; RecoveryScoreCalculator/ReadinessScoreCalculator
 * stay pure and DB-free so the scoring logic itself is unit-testable in
 * isolation from all of this.
 */
class RecoveryEngine
{
    public function __construct(
        private RecoveryFactorGateway $gateway,
        private RecoveryScoreCalculator $recoveryCalculator,
        private ReadinessScoreCalculator $readinessCalculator,
    ) {}

    /**
     * @return array{
     *     recovery: array{model: RecoveryScore, breakdown: list<array{key: string, label: string, contribution: int, insufficient_data: bool}>},
     *     readiness: array{model: ReadinessScore, breakdown: list<array{key: string, label: string, contribution: int, insufficient_data: bool}>},
     * }
     */
    public function calculateAndStoreForDate(User $user, Carbon $date): array
    {
        $recoveryResult = $this->recoveryCalculator->calculate($this->gateway->recoveryInputs($user, $date));
        $readinessResult = $this->readinessCalculator->calculate($this->gateway->readinessInputs($user, $date));

        $recoveryScore = RecoveryScore::updateOrCreate(
            ['user_id' => $user->id, 'date' => $date->toDateString()],
            [
                'score' => $recoveryResult['score'],
                'sleep_contribution' => $this->contributionFor($recoveryResult, 'sleep'),
                'hrv_contribution' => $this->contributionFor($recoveryResult, 'hrv'),
                'resting_heart_rate_contribution' => $this->contributionFor($recoveryResult, 'resting_hr'),
                'stress_contribution' => $this->contributionFor($recoveryResult, 'stress'),
                'training_load_contribution' => $this->contributionFor($recoveryResult, 'training_load'),
                'calculated_at' => now(),
            ]
        );

        $readinessScore = ReadinessScore::updateOrCreate(
            ['user_id' => $user->id, 'date' => $date->toDateString()],
            [
                'score' => $readinessResult['score'],
                'body_battery_contribution' => $this->contributionFor($readinessResult, 'body_battery'),
                'recent_activity_contribution' => $this->contributionFor($readinessResult, 'recent_activity'),
                'hrv_contribution' => $this->contributionFor($readinessResult, 'hrv'),
                'resting_heart_rate_contribution' => $this->contributionFor($readinessResult, 'resting_hr'),
                'calculated_at' => now(),
            ]
        );

        return [
            'recovery' => ['model' => $recoveryScore, 'breakdown' => $recoveryResult['factors']],
            'readiness' => ['model' => $readinessScore, 'breakdown' => $readinessResult['factors']],
        ];
    }

    public function calculateForDateRange(User $user, int $days): void
    {
        $today = Carbon::today();

        for ($i = 0; $i < $days; $i++) {
            $this->calculateAndStoreForDate($user, $today->copy()->subDays($i));
        }
    }

    /** @param array{factors: list<array{key: string, contribution: int, insufficient_data: bool}>} $result */
    private function contributionFor(array $result, string $key): ?float
    {
        foreach ($result['factors'] as $factor) {
            if ($factor['key'] === $key) {
                return $factor['insufficient_data'] ? null : (float) $factor['contribution'];
            }
        }

        return null;
    }
}
