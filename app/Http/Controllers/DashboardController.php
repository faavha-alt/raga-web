<?php

namespace App\Http\Controllers;

use App\Services\Dashboard\HealthTrendService;
use App\Services\Dashboard\InsightEngine;
use App\Services\Dashboard\RecentActivityService;
use App\Services\Dashboard\TodaySnapshotService;
use App\Services\Dashboard\WeeklyTrainingService;
use App\Services\Training\TrainingConsistencyService;
use App\Services\Training\TrainingGoalService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        private TodaySnapshotService $todaySnapshot,
        private RecentActivityService $recentActivity,
        private WeeklyTrainingService $weeklyTraining,
        private HealthTrendService $healthTrend,
        private InsightEngine $insights,
        private TrainingGoalService $goals,
        private TrainingConsistencyService $consistency,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $today = Carbon::today();

        $activeGoals = $user->trainingGoals()
            ->where('is_active', true)
            ->orderBy('created_at')
            ->get()
            ->take(3)
            ->map(fn ($goal) => [
                'goal' => $goal,
                'progress' => $this->goals->progressFor($user, $goal),
            ]);

        $snapshot = $this->todaySnapshot->forUser($user);

        // Baris "physiological readiness": recovery/readiness/consistency
        // dihitung engine RAGA sendiri, status beban dibaca dari `training_loads`
        // yang diisi perintah `training:analyze`.
        $consistency = $this->consistency->forPeriod($user, $today->copy()->subDays(29), $today);
        $trainingLoad = $user->trainingLoads()->whereDate('date', $today)->first();

        $scores = [
            'recovery' => $user->recoveryScores()->whereDate('date', $today)->first()?->score,
            'readiness' => $snapshot['readiness'],
            'consistency_percent' => $consistency['consistency_percent'],
            'consistency_streak' => $consistency['current_streak_days'],
            'load_ratio' => $trainingLoad?->acute_chronic_ratio,
            'load_risk' => $trainingLoad?->risk_level,
        ];

        return view('dashboard.index', [
            'today' => $snapshot,
            'recentWorkout' => $this->recentActivity->latestForUser($user),
            'recentSessions' => $user->workouts()->latest('start_date')->take(3)->get(),
            'week' => $this->weeklyTraining->summaryForUser($user),
            'peaks' => $this->weeklyTraining->lastSevenDays($user),
            'scores' => $scores,
            'garmin' => $user->garminConnection,
            'trendSeries' => $this->healthTrend->allSeries($user),
            'insights' => $this->insights->generate($user),
            'recommendations' => $user->recommendations()->where('is_read', false)->latest('date')->take(5)->get(),
            'goals' => $activeGoals,
        ]);
    }
}
