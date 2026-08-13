<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $healthScore = $user->healthScores()->latest('date')->first();
        $recoveryScore = $user->recoveryScores()->latest('date')->first();
        $sleep = $user->sleepSessions()->latest('bedtime')->first();
        $activity = $user->activitySummaries()->latest('date')->first();

        $restingHr = $user->vitalMeasurements()->where('type', 'resting_heart_rate')->latest('date')->first();
        $trainingReadiness = $user->vitalMeasurements()->where('type', 'training_readiness')->latest('date')->first();
        $stress = $user->vitalMeasurements()->where('type', 'stress_avg')->latest('date')->first();

        return view('dashboard.index', compact('healthScore', 'recoveryScore', 'sleep', 'activity', 'restingHr', 'trainingReadiness', 'stress'));
    }
}
