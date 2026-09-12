<?php

namespace App\Http\Controllers;

use App\Services\Social\ActivityFeedService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FeedController extends Controller
{
    public function __construct(private ActivityFeedService $feed) {}

    public function index(Request $request): View
    {
        return view('feed.index', [
            'activities' => $this->feed->feedFor($request->user()),
        ]);
    }
}
