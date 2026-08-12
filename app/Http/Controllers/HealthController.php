<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class HealthController extends Controller
{
    public function index(): View
    {
        $categories = ['Heart', 'Sleep', 'Activity', 'Body', 'Trends'];

        return view('health.index', compact('categories'));
    }
}
