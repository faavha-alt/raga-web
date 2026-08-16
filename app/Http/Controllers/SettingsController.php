<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class SettingsController extends Controller
{
    public function index(): View
    {
        $rows = ['HealthKit', 'Goals', 'Training Preferences', 'Privacy', 'Data'];

        return view('settings.index', compact('rows'));
    }
}
