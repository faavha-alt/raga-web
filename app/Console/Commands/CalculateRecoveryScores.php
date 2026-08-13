<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Recovery\RecoveryEngine;
use Illuminate\Console\Command;

class CalculateRecoveryScores extends Command
{
    protected $signature = 'recovery:calculate {--days=1 : How many days back to (re)calculate} {--user= : Target user ID (defaults to the first user)}';

    protected $description = 'Calculate and store Recovery/Readiness scores for the given user over the last N days';

    public function handle(RecoveryEngine $engine): int
    {
        $user = $this->option('user')
            ? User::findOrFail($this->option('user'))
            : User::oldest()->firstOrFail();

        $days = (int) $this->option('days');

        $engine->calculateForDateRange($user, $days);

        $this->info("Calculated recovery/readiness scores for {$days} day(s) for {$user->email}.");

        return self::SUCCESS;
    }
}
