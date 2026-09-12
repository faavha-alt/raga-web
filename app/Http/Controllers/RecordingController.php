<?php

namespace App\Http\Controllers;

use App\Services\Recording\RecordingService;
use App\Support\ActivityVisibility;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Perekaman aktivitas GPS langsung dari browser (pengganti Garmin).
 *
 * Halaman `/record` hanya merender shell UI; seluruh perekaman terjadi di
 * klien, lalu titik-titik GPS dikirim sebagai JSON ke `recordings.store`.
 */
class RecordingController extends Controller
{
    public function __construct(private RecordingService $recording) {}

    public function index(Request $request): View
    {
        return view('recording.index', [
            'sportTypes' => RecordingService::SPORT_TYPES,
            'visibilityOptions' => ActivityVisibility::cases(),
        ]);
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $data = $this->validatePayload($request);

        $this->ensurePointsAreUsable($data);

        $workout = $this->recording->create($request->user(), $data);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Aktivitas berhasil disimpan.',
                'workout_id' => $workout->id,
                'redirect' => route('activities.show', $workout),
            ], 201);
        }

        return redirect()
            ->route('activities.show', $workout)
            ->with('status', 'Aktivitas berhasil disimpan.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePayload(Request $request): array
    {
        return $request->validate([
            'type' => ['required', 'string', Rule::in(RecordingService::SPORT_TYPES)],
            'name' => ['nullable', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'visibility' => ['nullable', Rule::in(ActivityVisibility::values())],
            'started_at' => ['required', 'date'],
            'elapsed_seconds' => ['nullable', 'integer', 'min:0', 'max:'.RecordingService::MAX_TRACK_DURATION_SECONDS],
            'manual_distance_meters' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'manual_duration_seconds' => ['nullable', 'integer', 'min:0', 'max:86400'],
            'points' => ['present', 'array', 'max:'.RecordingService::MAX_POINTS],
            'points.*.t' => ['required', 'integer', 'min:0', 'max:'.RecordingService::MAX_POINT_TIMESTAMP_MS],
            'points.*.lat' => ['required', 'numeric', 'between:-90,90'],
            'points.*.lng' => ['required', 'numeric', 'between:-180,180'],
            'points.*.alt' => ['nullable', 'numeric', 'between:-500,9000'],
            'points.*.hr' => ['nullable', 'numeric', 'between:0,300'],
            'points.*.cadence' => ['nullable', 'numeric', 'between:0,300'],
        ], [
            'type.required' => 'Jenis olahraga wajib dipilih.',
            'type.in' => 'Jenis olahraga tidak dikenal.',
            'name.max' => 'Judul aktivitas terlalu panjang (maksimal 120 karakter).',
            'description.max' => 'Deskripsi terlalu panjang (maksimal 2000 karakter).',
            'visibility.in' => 'Nilai visibilitas tidak dikenal.',
            'started_at.required' => 'Waktu mulai aktivitas tidak ditemukan.',
            'started_at.date' => 'Format waktu mulai tidak valid.',
            'elapsed_seconds.integer' => 'Durasi harus berupa angka detik.',
            'elapsed_seconds.max' => 'Durasi melebihi batas 72 jam.',
            'manual_distance_meters.numeric' => 'Jarak manual harus berupa angka.',
            'manual_distance_meters.max' => 'Jarak manual terlalu besar.',
            'manual_duration_seconds.integer' => 'Durasi manual harus berupa angka detik.',
            'points.present' => 'Data titik GPS tidak dikirim.',
            'points.array' => 'Format titik GPS tidak valid.',
            'points.max' => 'Terlalu banyak titik GPS (maksimal :max).',
            'points.*.t.required' => 'Setiap titik GPS harus memiliki waktu.',
            'points.*.t.integer' => 'Waktu titik GPS tidak valid.',
            'points.*.t.max' => 'Waktu titik GPS di luar jangkauan yang wajar.',
            'points.*.lat.required' => 'Setiap titik GPS harus memiliki lintang.',
            'points.*.lat.numeric' => 'Lintang titik GPS harus berupa angka.',
            'points.*.lat.between' => 'Lintang titik GPS harus antara -90 dan 90 derajat.',
            'points.*.lng.required' => 'Setiap titik GPS harus memiliki bujur.',
            'points.*.lng.numeric' => 'Bujur titik GPS harus berupa angka.',
            'points.*.lng.between' => 'Bujur titik GPS harus antara -180 dan 180 derajat.',
            'points.*.alt.between' => 'Ketinggian titik GPS di luar rentang wajar.',
            'points.*.hr.between' => 'Detak jantung titik GPS di luar rentang wajar.',
            'points.*.cadence.between' => 'Kadens titik GPS di luar rentang wajar.',
        ]);
    }

    /**
     * Aturan yang tidak bisa dinyatakan sebagai rule validasi biasa: minimal 2
     * titik (kecuali mode manual), dan waktu yang naik monoton.
     *
     * @param  array<string, mixed>  $data
     */
    private function ensurePointsAreUsable(array $data): void
    {
        $points = $data['points'];

        if (count($points) < 2) {
            $hasManualData = isset($data['manual_distance_meters'], $data['manual_duration_seconds']);

            if (! $hasManualData) {
                throw ValidationException::withMessages([
                    'points' => 'Rekaman membutuhkan minimal 2 titik GPS, atau isi jarak dan durasi manual.',
                ]);
            }

            return;
        }

        // Durasi total trek (titik terakhir - titik pertama) tidak boleh
        // melewati batas; ini juga menutup celah bila titik pertama tidak nol.
        $span = (int) $points[count($points) - 1]['t'] - (int) $points[0]['t'];

        if ($span > RecordingService::MAX_TRACK_DURATION_MS) {
            throw ValidationException::withMessages([
                'points' => 'Durasi rekaman melebihi batas 72 jam.',
            ]);
        }

        $previous = null;

        foreach ($points as $point) {
            if ($previous !== null && (int) $point['t'] <= $previous) {
                throw ValidationException::withMessages([
                    'points' => 'Waktu titik GPS harus urut naik.',
                ]);
            }

            $previous = (int) $point['t'];
        }
    }
}
