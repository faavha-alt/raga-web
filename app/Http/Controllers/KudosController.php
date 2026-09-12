<?php

namespace App\Http\Controllers;

use App\Models\Kudos;
use App\Models\User;
use App\Models\Workout;
use App\Notifications\NewKudos;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class KudosController extends Controller
{
    public function store(Request $request, Workout $workout): RedirectResponse|Response
    {
        $viewer = $request->user();
        $this->ensureVisible($workout, $viewer);

        $kudos = Kudos::firstOrCreate([
            'user_id' => $viewer->id,
            'workout_id' => $workout->id,
        ]);

        // Notifikasi hanya untuk kudos BARU, dan tidak pernah ke diri sendiri.
        if ($kudos->wasRecentlyCreated && $workout->user_id !== $viewer->id) {
            $workout->user->notify(new NewKudos($viewer, $workout));
        }

        return $this->respond($request, 'Kudos diberikan.');
    }

    public function destroy(Request $request, Workout $workout): RedirectResponse|Response
    {
        $viewer = $request->user();
        $this->ensureVisible($workout, $viewer);

        // Menghapus kudos yang tidak ada bukan error.
        Kudos::query()
            ->where('user_id', $viewer->id)
            ->where('workout_id', $workout->id)
            ->delete();

        return $this->respond($request, 'Kudos dihapus.');
    }

    /**
     * Aktivitas yang tidak boleh dilihat mengembalikan 404 (bukan 403)
     * supaya keberadaannya tidak terkonfirmasi.
     */
    private function ensureVisible(Workout $workout, User $viewer): void
    {
        $workout->loadMissing('user');

        abort_unless($workout->isVisibleTo($viewer), 404);
    }

    private function respond(Request $request, string $message): RedirectResponse|Response
    {
        if ($request->expectsJson()) {
            return response()->noContent();
        }

        return back()->with('status', $message);
    }
}
