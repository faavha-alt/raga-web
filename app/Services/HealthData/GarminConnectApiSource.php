<?php

namespace App\Services\HealthData;

use App\Exceptions\HealthDataSourceUnavailableException;
use App\Models\User;

/**
 * Placeholder for the official Garmin Connect Health API integration.
 * Not wired up yet — developer access has not been approved. Every
 * method fails loudly instead of pretending to sync data.
 */
class GarminConnectApiSource implements HealthDataSource
{
    public function name(): string
    {
        return 'garmin_connect';
    }

    public function isAvailable(): bool
    {
        return false;
    }

    public function syncActivitySummaries(User $user): int
    {
        throw new HealthDataSourceUnavailableException(
            'Garmin Connect API access has not been approved yet.'
        );
    }

    public function syncWorkouts(User $user): int
    {
        throw new HealthDataSourceUnavailableException(
            'Garmin Connect API access has not been approved yet.'
        );
    }

    public function syncSleepSessions(User $user): int
    {
        throw new HealthDataSourceUnavailableException(
            'Garmin Connect API access has not been approved yet.'
        );
    }
}
