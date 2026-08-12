<?php

return [
    // Which HealthDataSource implementation is active. Switches to
    // 'garmin' once Garmin Connect API access is approved.
    'source' => env('HEALTH_DATA_SOURCE', 'manual'),

    'sources' => [
        'manual' => \App\Services\HealthData\ManualCsvImportSource::class,
        'garmin' => \App\Services\HealthData\GarminConnectApiSource::class,
    ],
];
