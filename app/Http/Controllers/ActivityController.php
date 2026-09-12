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
        $viewer = $request->user();

        // Gerbang privasi: aktivitas yang tidak boleh dilihat -> 404 (bukan 403)
        // supaya keberadaannya tidak terkonfirmasi.
        $workout->loadMissing('user');

        if (! $workout->isVisibleTo($viewer)) {
            throw new NotFoundHttpException;
        }

        $workout->load(['laps', 'comments.user']);
        $workout->loadCount(['kudos', 'comments']);

        $charts = $this->activityDetail->chartsFor($workout);

        // Privasi kesehatan: seri detak jantung (data kesehatan per-sampel)
        // hanya boleh diterima pemilik aktivitas. Seri non-kesehatan
        // (pace/elevation) tetap dikirim seperti semula.
        if ($viewer->id !== $workout->user_id) {
            unset($charts['heart_rate']);
        }

        return view('activities.show', [
            'workout' => $workout,
            'charts' => $charts,
            'routePoints' => $this->activityDetail->routePoints($workout),
            'laps' => $workout->laps,
            'comments' => $workout->comments,
            'kudosCount' => (int) $workout->kudos_count,
            'commentsCount' => (int) $workout->comments_count,
            'viewerHasKudos' => $workout->kudos()->where('user_id', $viewer->id)->exists(),
            'viewer' => $viewer,
        ]);
    }
}
