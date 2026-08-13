<?php

namespace App\Http\Controllers;

use App\Models\Workout;
use App\Services\Activity\ActivityDetailService;
use App\Services\Activity\ActivityQueryService;
use App\Services\Activity\ActivitySummaryService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ActivityController extends Controller
{
    public function __construct(
        private ActivityQueryService $activityQuery,
        private ActivitySummaryService $activitySummary,
        private ActivityDetailService $activityDetail,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();

        $filters = $request->only(['search', 'type', 'from', 'to', 'sort', 'direction']);

        $activities = $this->activityQuery->forUser($user, $filters)
            ->paginate(15)
            ->withQueryString();

        $summary = $this->activitySummary->summarize($user, $filters);
        $types = $this->activityQuery->typesForUser($user);

        return view('activities.index', [
            'activities' => $activities,
            'summary' => $summary,
            'types' => $types,
            'filters' => $filters,
        ]);
    }

    public function show(Request $request, Workout $workout): View
    {
        if ($workout->user_id !== $request->user()->id) {
            throw new NotFoundHttpException;
        }

        return view('activities.show', [
            'workout' => $workout,
            'charts' => $this->activityDetail->chartsFor($workout),
        ]);
    }
}
