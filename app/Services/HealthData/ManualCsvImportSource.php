<?php

namespace App\Services\HealthData;

use App\Models\User;

/**
 * Interim data source while Garmin Connect API access is pending:
 * user manually exports CSV from Garmin Connect / Apple Health and
 * uploads it. Parsing/import is implemented in a later phase — this
 * class only claims the seam so the rest of the app can depend on
 * HealthDataSource without knowing which provider is active.
 */
class ManualCsvImportSource implements HealthDataSource
{
    public function name(): string
    {
        return 'manual_csv';
    }

    public function isAvailable(): bool
    {
        return true;
    }

    public function syncActivitySummaries(User $user): int
    {
        throw new \RuntimeException('CSV import is not implemented yet.');
    }

    public function syncWorkouts(User $user): int
    {
        throw new \RuntimeException('CSV import is not implemented yet.');
    }

    public function syncSleepSessions(User $user): int
    {
        throw new \RuntimeException('CSV import is not implemented yet.');
    }
}
