<?php

namespace App\Services\HealthData;

use App\Models\User;

/**
 * Contract every health data provider implements, so the app never
 * depends on Garmin/HealthKit specifics directly. Per the product spec:
 * don't assume the Garmin API is available until it's actually verified.
 */
interface HealthDataSource
{
    public function name(): string;

    public function isAvailable(): bool;

    public function syncActivitySummaries(User $user): int;

    public function syncWorkouts(User $user): int;

    public function syncSleepSessions(User $user): int;
}
