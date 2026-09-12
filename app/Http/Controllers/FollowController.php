<?php

namespace App\Http\Controllers;

use App\Models\Follow;
use App\Models\User;
use App\Notifications\NewFollower;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class FollowController extends Controller
{
    public function store(Request $request, User $user): RedirectResponse
    {
        $viewer = $request->user();

        abort_if($viewer->id === $user->id, 403);

        // firstOrCreate menjaga idempotensi terhadap unique index
        // (follower_id, following_id) — follow ulang tidak error/duplikat.
        $follow = Follow::firstOrCreate([
            'follower_id' => $viewer->id,
            'following_id' => $user->id,
        ]);

        if ($follow->wasRecentlyCreated) {
            $user->notify(new NewFollower($viewer));
        }

        return back()->with('status', 'Anda mulai mengikuti '.$user->name.'.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        $viewer = $request->user();

        abort_if($viewer->id === $user->id, 403);

        // Menghapus saat belum mengikuti bukan error.
        Follow::query()
            ->where('follower_id', $viewer->id)
            ->where('following_id', $user->id)
            ->delete();

        return back()->with('status', 'Anda berhenti mengikuti '.$user->name.'.');
    }
}
