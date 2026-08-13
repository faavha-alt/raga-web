<?php

namespace App\Services\Dashboard;

use App\Models\User;
use App\Models\Workout;
use Illuminate\Support\Carbon;

class RecentActivityService
{
    /**
     * Today's workout if there is one, otherwise the most recent workout
     * on record (with how long ago it was, so the view can be honest about
     * it not being "today").
     */
    public function latestForUser(User $user): ?Workout
    {
        $today = $user->workouts()->whereDate('start_date', Carbon::today())->latest('start_date')->first();

        return $today ?? $user->workouts()->latest('start_date')->first();
    }
}
