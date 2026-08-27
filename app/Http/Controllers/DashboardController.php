<?php

namespace App\Http\Controllers;

use App\Services\Dashboard\HealthTrendService;
use App\Services\Dashboard\InsightEngine;
use App\Services\Dashboard\RecentActivityService;
use App\Services\Dashboard\TodaySnapshotService;
use App\Services\Dashboard\WeeklyTrainingService;
use App\Services\Training\TrainingGoalService;
use Illuminate\Http\Request;
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
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();

        $activeGoals = $user->trainingGoals()
            ->where('is_active', true)
            ->orderBy('created_at')
            ->get()
            ->take(3)
            ->map(fn ($goal) => [
                'goal' => $goal,
                'progress' => $this->goals->progressFor($user, $goal),
            ]);

        return view('dashboard.index', [
            'today' => $this->todaySnapshot->forUser($user),
            'recentWorkout' => $this->recentActivity->latestForUser($user),
            'week' => $this->weeklyTraining->summaryForUser($user),
            'trendSeries' => $this->healthTrend->allSeries($user),
            'insights' => $this->insights->generate($user),
            'recommendations' => $user->recommendations()->where('is_read', false)->latest('date')->take(5)->get(),
            'goals' => $activeGoals,
        ]);
    }
}
