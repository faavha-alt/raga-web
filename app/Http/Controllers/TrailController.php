<?php

namespace App\Http\Controllers;

use App\Models\Workout;
use App\Services\Activity\ActivityDetailService;
use App\Services\Health\PersonalBaselineService;
use App\Services\Trail\TrailElevationProfileService;
use App\Services\Trail\TrailMovingTimeCalculator;
use App\Services\Trail\TrailRouteGroupingService;
use App\Services\Trail\TrailSeriesService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class TrailController extends Controller
{
    private const TYPE = 'trail_running';

    public function __construct(
        private TrailSeriesService $series,
        private TrailMovingTimeCalculator $movingTime,
        private TrailElevationProfileService $elevationProfile,
        private TrailRouteGroupingService $routeGrouping,
        private ActivityDetailService $activityDetail,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $today = Carbon::today();
        $from = $today->copy()->subDays(89);

        $totals = $this->series->totalsForRange($user, $from, $today);

        $recentTrailRuns = $user->workouts()
            ->where('type', self::TYPE)
            ->whereBetween('start_date', [$from, $today->copy()->endOfDay()])
            ->orderByDesc('start_date')
            ->get();

        $movingSeconds = $recentTrailRuns->sum(fn ($workout) => $this->movingTime->movingSecondsForWorkout($workout));
        $repeatedRouteCount = count($this->routeGrouping->repeatedRoutes($user));
        $recentRuns = $recentTrailRuns->take(10);

        return view('trail.index', compact('totals', 'movingSeconds', 'repeatedRouteCount', 'recentRuns'));
    }

    public function show(Request $request, Workout $workout): View
    {
        if ($workout->user_id !== $request->user()->id || $workout->type !== self::TYPE) {
            throw new NotFoundHttpException;
        }

        $profile = $this->elevationProfile->profileFor($workout);
        $movingSeconds = $this->movingTime->movingSecondsForWorkout($workout);
        $routePoints = $this->activityDetail->routePoints($workout);

        return view('trail.show', compact('workout', 'profile', 'movingSeconds', 'routePoints'));
    }

    public function routes(Request $request): View
    {
        $routeGroups = $this->routeGrouping->repeatedRoutes($request->user());
        $mapColors = ['#6C5CE7', '#1baf7a', '#e34948', '#eda100', '#2a78d6'];

        $mapRoutesByGroup = collect($routeGroups)->mapWithKeys(function ($group) use ($mapColors) {
            $mapRoutes = collect($group['runs'])->values()->map(fn ($run, $i) => [
                'label' => $run['workout']->start_date->translatedFormat('d M Y'),
                'color' => $mapColors[$i % count($mapColors)],
                'points' => $this->activityDetail->routePoints($run['workout']),
            ])->all();

            return [$group['name'] => $mapRoutes];
        });

        return view('trail.routes', [
            'routeGroups' => $routeGroups,
            'mapRoutesByGroup' => $mapRoutesByGroup,
            'disclaimer' => PersonalBaselineService::DISCLAIMER,
        ]);
    }
}
