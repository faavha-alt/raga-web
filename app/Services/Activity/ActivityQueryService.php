<?php

namespace App\Services\Activity;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class ActivityQueryService
{
    private const SORTABLE = [
        'date' => 'start_date',
        'distance' => 'distance_meters',
        'calories' => 'active_calories',
        'avg_hr' => 'average_heart_rate',
        'duration' => 'duration_seconds',
    ];

    /** Filtered + sorted, ready to paginate. */
    public function forUser(User $user, array $filters): Builder
    {
        $query = $this->applyFilters($user->workouts()->newQuery(), $filters);

        $query->addSelect('workouts.*')
            ->selectRaw('ABS(TIMESTAMPDIFF(SECOND, start_date, end_date)) as duration_seconds');

        $sortKey = $filters['sort'] ?? 'date';
        $direction = ($filters['direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
        $column = self::SORTABLE[$sortKey] ?? self::SORTABLE['date'];

        return $query->orderBy($column, $direction);
    }

    /** Same filters, no select/order — for ActivitySummaryService to aggregate independently. */
    public function applyFilters(Builder $query, array $filters): Builder
    {
        if (! empty($filters['search'])) {
            $query->where('type', 'like', '%'.$filters['search'].'%');
        }

        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (! empty($filters['from'])) {
            $query->whereDate('start_date', '>=', $filters['from']);
        }

        if (! empty($filters['to'])) {
            $query->whereDate('start_date', '<=', $filters['to']);
        }

        return $query;
    }

    /** @return list<string> */
    public function typesForUser(User $user): array
    {
        return $user->workouts()
            ->select('type')
            ->distinct()
            ->orderBy('type')
            ->pluck('type')
            ->all();
    }

    public function sortOptions(): array
    {
        return array_keys(self::SORTABLE);
    }
}
