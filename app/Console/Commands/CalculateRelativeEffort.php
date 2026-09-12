<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Training\RelativeEffortCalculator;
use Illuminate\Console\Command;

class CalculateRelativeEffort extends Command
{
    protected $signature = 'training:calculate-relative-effort
        {--user= : Target user ID (defaults to every user)}
        {--days= : Only recalculate workouts started within the last N days (defaults to all)}';

    protected $description = 'Calculate and store Relative Effort (zone-weighted TRIMP) for workouts that have per-second heart rate data';

    public function handle(RelativeEffortCalculator $calculator): int
    {
        $users = $this->option('user')
            ? User::whereKey($this->option('user'))->get()
            : User::all();

        if ($users->isEmpty()) {
            $this->error('No users found.');

            return self::FAILURE;
        }

        $days = $this->option('days') !== null ? max(1, (int) $this->option('days')) : null;
        $processed = 0;
        $scored = 0;

        foreach ($users as $user) {
            $userProcessed = 0;
            $userScored = 0;

            $this->workoutQuery($user, $days)
                ->chunkById(100, function ($workouts) use ($calculator, &$userProcessed, &$userScored): void {
                    foreach ($workouts as $workout) {
                        $userProcessed++;

                        if ($calculator->updateWorkout($workout) !== null) {
                            $userScored++;
                        }
                    }
                });

            $processed += $userProcessed;
            $scored += $userScored;

            $this->info("{$user->email}: {$userScored}/{$userProcessed} aktivitas mendapat Relative Effort.");
        }

        $this->info("Selesai. Total {$scored}/{$processed} aktivitas dihitung.");

        return self::SUCCESS;
    }

    /**
     * Hanya aktivitas yang punya HR per-detik yang bisa dihitung — menyaring di
     * query supaya command tidak memuat ribuan aktivitas tanpa sampel HR.
     */
    private function workoutQuery(User $user, ?int $days)
    {
        $query = $user->workouts()
            ->whereHas('samples', fn ($query) => $query->whereNotNull('heart_rate'))
            ->orderBy('id');

        if ($days !== null) {
            $query->where('start_date', '>=', now()->subDays($days));
        }

        return $query;
    }
}
