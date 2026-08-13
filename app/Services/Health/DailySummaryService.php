<?php

namespace App\Services\Health;

use App\Models\User;

class DailySummaryService
{
    private const DAYS = 14;

    public function __construct(private MetricSeriesService $series) {}

    /**
     * One row per date (most recent first), each with a value per requested
     * metric where available.
     *
     * @param  list<string>  $metrics
     * @return list<array<string, mixed>>
     */
    public function forMetrics(User $user, array $metrics): array
    {
        $byDate = [];

        foreach ($metrics as $metric) {
            foreach ($this->series->seriesFor($user, $metric, self::DAYS) as $point) {
                $byDate[$point['date']][$metric] = $point['value'];
            }
        }

        krsort($byDate);

        $rows = [];
        foreach ($byDate as $date => $values) {
            $rows[] = ['date' => $date, 'values' => $values];
        }

        return $rows;
    }
}
