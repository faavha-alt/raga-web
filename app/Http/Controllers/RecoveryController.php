<?php

namespace App\Http\Controllers;

use App\Services\Health\PersonalBaselineService;
use App\Services\Recovery\RecoveryEngine;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class RecoveryController extends Controller
{
    private const HISTORY_DAYS = 90;

    public function __construct(private RecoveryEngine $engine) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $today = Carbon::today();

        $result = $this->engine->calculateAndStoreForDate($user, $today);

        $since = $today->copy()->subDays(self::HISTORY_DAYS - 1);

        $recoveryHistory = $user->recoveryScores()
            ->where('date', '>=', $since)
            ->orderBy('date')
            ->get()
            ->map(fn ($r) => ['date' => $r->date->toDateString(), 'value' => (float) $r->score])
            ->values()
            ->all();

        $readinessHistory = $user->readinessScores()
            ->where('date', '>=', $since)
            ->orderBy('date')
            ->get()
            ->map(fn ($r) => ['date' => $r->date->toDateString(), 'value' => (float) $r->score])
            ->values()
            ->all();

        $trendSeries = [
            'recovery' => ['label' => 'Recovery Score', 'unit' => '', 'color' => '#4a3aa7', 'decimals' => 0, 'points' => $recoveryHistory],
            'readiness' => ['label' => 'Readiness Score', 'unit' => '', 'color' => '#1baf7a', 'decimals' => 0, 'points' => $readinessHistory],
        ];

        return view('recovery.index', [
            'recovery' => $result['recovery'],
            'readiness' => $result['readiness'],
            'trendSeries' => $trendSeries,
            'disclaimer' => PersonalBaselineService::DISCLAIMER,
        ]);
    }
}
