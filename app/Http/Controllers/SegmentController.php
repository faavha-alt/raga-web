<?php

namespace App\Http\Controllers;

use App\Models\Segment;
use App\Models\Workout;
use App\Services\Segment\PolylineCodec;
use App\Services\Segment\SegmentLeaderboardService;
use App\Services\Segment\SegmentQueryService;
use App\Services\Segment\SegmentScanService;
use App\Services\Segment\SegmentTrackBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class SegmentController extends Controller
{
    /** Panjang minimum segment agar layak diperlombakan. */
    private const MIN_DISTANCE_METERS = 100.0;

    public function __construct(
        private SegmentTrackBuilder $trackBuilder,
        private SegmentScanService $scanner,
        private SegmentQueryService $query,
        private SegmentLeaderboardService $leaderboard,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $filters = $request->only(['search', 'activity_type', 'sort', 'direction']);

        $segments = $this->query->browseFor($user, $filters)
            ->paginate(15)
            ->withQueryString();

        return view('segments.index', [
            'segments' => $segments,
            'filters' => $filters,
            'activityTypes' => $this->query->activityTypes($user),
        ]);
    }

    public function create(Request $request): View
    {
        $user = $request->user();

        $workouts = $user->workouts()
            ->whereHas('samples', function ($query): void {
                $query->whereNotNull('latitude')->whereNotNull('longitude');
            })
            ->orderByDesc('start_date')
            ->limit(50)
            ->get(['id', 'name', 'type', 'start_date', 'distance_meters']);

        $selectedWorkout = null;
        $points = [];

        if ($request->filled('workout_id')) {
            $selectedWorkout = $this->ownedWorkout($request, $request->integer('workout_id'));
            $points = $this->trackBuilder->pointsForWorkout($selectedWorkout);
        }

        return view('segments.create', [
            'workouts' => $workouts,
            'selectedWorkout' => $selectedWorkout,
            'points' => $points,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'workout_id' => ['required', 'integer'],
            'start_index' => ['required', 'integer', 'min:0'],
            'end_index' => ['required', 'integer', 'min:0'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'start_label' => ['nullable', 'string', 'max:255'],
            'end_label' => ['nullable', 'string', 'max:255'],
            'is_public' => ['nullable', 'boolean'],
        ]);

        $workout = $this->ownedWorkout($request, (int) $data['workout_id']);
        $points = $this->trackBuilder->pointsForWorkout($workout);

        if (count($points) < 2) {
            throw ValidationException::withMessages([
                'workout_id' => 'Aktivitas ini tidak punya cukup data GPS untuk dibuat segment.',
            ]);
        }

        $startIndex = (int) $data['start_index'];
        $endIndex = (int) $data['end_index'];

        if ($startIndex >= $endIndex) {
            throw ValidationException::withMessages([
                'end_index' => 'Titik akhir harus berada setelah titik awal pada lintasan.',
            ]);
        }

        if ($endIndex >= count($points)) {
            throw ValidationException::withMessages([
                'end_index' => 'Titik yang dipilih berada di luar lintasan aktivitas.',
            ]);
        }

        $geometry = $this->trackBuilder->build($points, $startIndex, $endIndex);

        if ($geometry['distance_meters'] < self::MIN_DISTANCE_METERS) {
            throw ValidationException::withMessages([
                'end_index' => 'Segment terlalu pendek — minimal 100 meter.',
            ]);
        }

        // Pembuatan segment + pemindaian + sinkronisasi PB harus atomik:
        // `syncPersonalBests` sempat mengosongkan flag PB semua effort lalu
        // mengisinya ulang, sehingga kegagalan di tengah bisa merusak data.
        $segment = DB::transaction(function () use ($request, $data, $workout, $geometry) {
            $segment = $request->user()->ownedSegments()->create([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'activity_type' => $workout->type,
                'distance_meters' => $geometry['distance_meters'],
                'elevation_gain_meters' => $geometry['elevation_gain_meters'],
                'average_grade_percent' => $geometry['average_grade_percent'],
                'start_lat' => $geometry['start_lat'],
                'start_lng' => $geometry['start_lng'],
                'end_lat' => $geometry['end_lat'],
                'end_lng' => $geometry['end_lng'],
                'start_label' => $data['start_label'] ?? $workout->location_name,
                'end_label' => $data['end_label'] ?? $workout->location_name,
                'encoded_polyline' => $geometry['encoded_polyline'],
                'is_public' => $request->boolean('is_public'),
            ]);

            $this->scanner->scan($segment);

            return $segment;
        });

        return redirect()->route('segments.show', $segment)->with('status', 'Segment berhasil dibuat.');
    }

    public function show(Request $request, Segment $segment): View
    {
        $viewer = $request->user();

        if (! $segment->is_public && $segment->user_id !== $viewer->id) {
            throw new NotFoundHttpException;
        }

        $leaderboard = $this->leaderboard->forSegment($segment, $viewer);

        return view('segments.show', [
            'segment' => $segment,
            'routePoints' => PolylineCodec::decode((string) $segment->encoded_polyline),
            'leaderboard' => $leaderboard,
            'myEfforts' => $this->leaderboard->myEfforts($segment, $viewer),
            'visibleEffortCount' => $this->leaderboard->visibleEffortCount($segment, $viewer),
        ]);
    }

    public function destroy(Request $request, Segment $segment): RedirectResponse
    {
        // 404 (bukan 403) agar keberadaan segment milik orang lain tidak
        // terkonfirmasi lewat perbedaan status.
        if ($segment->user_id !== $request->user()->id) {
            throw new NotFoundHttpException;
        }

        $segment->delete();

        return redirect()->route('segments.index')->with('status', 'Segment dihapus.');
    }

    public function rescan(Request $request, Segment $segment): RedirectResponse
    {
        if ($segment->user_id !== $request->user()->id) {
            throw new NotFoundHttpException;
        }

        $matched = $this->scanner->scan($segment);

        return redirect()->route('segments.show', $segment)
            ->with('status', $matched.' usaha baru ditemukan untuk segment ini.');
    }

    /**
     * Hanya PEMILIK aktivitas yang boleh membuat segment dari datanya —
     * `visibleTo` tidak cukup di sini.
     */
    private function ownedWorkout(Request $request, int $workoutId): Workout
    {
        return $request->user()->workouts()->findOrFail($workoutId);
    }
}
