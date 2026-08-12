<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class TrainingController extends Controller
{
    public function index(Request $request): View
    {
        $plans = $request->user()->trainingPlans()->latest('start_date')->get();

        return view('training.index', compact('plans'));
    }
}
