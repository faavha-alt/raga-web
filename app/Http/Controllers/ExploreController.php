<?php

namespace App\Http\Controllers;

use App\Models\Follow;
use App\Models\User;
use App\Services\Social\ActivityFeedService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ExploreController extends Controller
{
    public function __construct(private ActivityFeedService $feed) {}

    public function index(Request $request): View
    {
        $viewer = $request->user();

        $followedIds = Follow::query()
            ->where('follower_id', $viewer->id)
            ->select('following_id');

        $suggestions = User::query()
            ->whereKeyNot($viewer->id)
            ->whereNotNull('username')
            // Hanya profil publik, dan hanya yang belum diikuti — daftar ini
            // memang "atlet untuk diikuti", jadi menampilkan orang yang sudah
            // diikuti tidak ada gunanya.
            ->where('is_public', true)
            ->whereNotIn('id', $followedIds)
            ->withCount('followers')
            ->orderByDesc('followers_count')
            ->orderBy('name')
            ->limit(12)
            ->get();

        return view('explore.index', [
            'suggestions' => $suggestions,
            'activities' => $this->feed->publicPreview($viewer),
        ]);
    }
}
