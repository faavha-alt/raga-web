<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Training\TrainingStatusEngine;
use Illuminate\Console\Command;

class AnalyzeTrainingLoad extends Command
{
    protected $signature = 'training:analyze {--days=1 : How many days back to (re)calculate} {--user= : Target user ID (defaults to the first user)}';

    protected $description = 'Calculate and store acute:chronic training load / monotony / risk level for the given user over the last N days';

    public function handle(TrainingStatusEngine $engine): int
    {
        $user = $this->option('user')
            ? User::findOrFail($this->option('user'))
            : User::oldest()->firstOrFail();

        $days = (int) $this->option('days');

        $engine->calculateForDateRange($user, $days);

        $this->info("Calculated training load analysis for {$days} day(s) for {$user->email}.");

        return self::SUCCESS;
    }
}
