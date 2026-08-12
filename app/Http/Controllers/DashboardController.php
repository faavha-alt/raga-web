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

        return view('dashboard.index', compact('healthScore', 'recoveryScore', 'sleep', 'activity'));
    }
}
