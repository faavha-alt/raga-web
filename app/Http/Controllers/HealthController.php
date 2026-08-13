<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class HealthController extends Controller
{
    private const VITAL_TYPES = [
        'resting_heart_rate',
        'hrv_overnight_avg',
        'stress_avg',
        'respiration_rate',
        'training_readiness',
        'body_battery_charged',
        'body_battery_drained',
        'vo2max',
        'spo2_avg',
    ];

    public function index(Request $request): View
    {
        $user = $request->user();

        $latestVitals = collect(self::VITAL_TYPES)->mapWithKeys(
            fn (string $type) => [$type => $user->vitalMeasurements()->where('type', $type)->latest('date')->first()]
        );

        $sleep = $user->sleepSessions()->latest('bedtime')->first();
        $bodyMeasurement = $user->bodyMeasurements()->latest('date')->first();
        $activityWeek = $user->activitySummaries()->latest('date')->take(7)->get();
        $heartRateToday = $user->heartRateSamples()->whereDate('timestamp', now())->orderBy('timestamp')->get();

        return view('health.index', compact('latestVitals', 'sleep', 'bodyMeasurement', 'activityWeek', 'heartRateToday'));
    }
}
