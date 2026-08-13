<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class TrainingController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $plans = $user->trainingPlans()->latest('start_date')->get();
        $recentWorkouts = $user->workouts()->latest('start_date')->take(15)->get();
        $personalRecords = $user->personalRecords()->orderBy('type')->get();

        return view('training.index', compact('plans', 'recentWorkouts', 'personalRecords'));
    }
}
