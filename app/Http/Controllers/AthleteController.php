<?php

namespace App\Http\Controllers;

use App\Models\Follow;
use App\Models\User;
use App\Services\Social\ActivityFeedService;
use App\Services\Social\AthleteStatsService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AthleteController extends Controller
{
    public function __construct(
        private ActivityFeedService $feed,
        private AthleteStatsService $stats,
    ) {}

    public function show(Request $request, User $user): View
    {
        // Pengguna tanpa username tidak boleh bisa dijangkau (bind by username).
        abort_if($user->username === null, 404);

        $viewer = $request->user();
        $canViewProfile = $this->canViewProfile($user, $viewer);

        if (! $canViewProfile) {
            return view('athletes.private', [
                'athlete' => $user,
                'viewer' => $viewer,
            ]);
        }

        return view('athletes.show', [
            'athlete' => $user,
            'viewer' => $viewer,
            'stats' => $this->stats->forUser($user, $viewer),
            'activities' => $this->feed->forAthlete($user, $viewer),
            'isFollowing' => $viewer->id !== $user->id && $viewer->isFollowing($user),
            'followerCount' => $user->followers()->count(),
            'followingCount' => $user->following()->count(),
        ]);
    }

    public function followers(Request $request, User $user): View
    {
        return $this->relationships($request, $user, followers: true);
    }

    public function following(Request $request, User $user): View
    {
        return $this->relationships($request, $user, followers: false);
    }

    private function relationships(Request $request, User $user, bool $followers): View
    {
        $viewer = $request->user();

        if (! $this->canViewProfile($user, $viewer)) {
            return view('athletes.private', [
                'athlete' => $user,
                'viewer' => $viewer,
            ]);
        }

        $people = ($followers ? $user->followerUsers() : $user->followingUsers())
            ->orderBy('name')
            ->paginate(20);

        $followedIds = Follow::query()
            ->where('follower_id', $viewer->id)
            ->whereIn('following_id', $people->pluck('id'))
            ->pluck('following_id')
            ->all();

        return view('athletes.relationships', [
            'athlete' => $user,
            'viewer' => $viewer,
            'people' => $people,
            'followedIds' => $followedIds,
            'title' => $followers ? 'Pengikut' : 'Mengikuti',
            'isFollowers' => $followers,
        ]);
    }

    /**
     * Profil privat (is_public = false) hanya boleh dibuka pemilik dan pengikutnya.
     */
    private function canViewProfile(User $athlete, User $viewer): bool
    {
        if ($viewer->id === $athlete->id) {
            return true;
        }

        return $athlete->is_public || $viewer->isFollowing($athlete);
    }
}
