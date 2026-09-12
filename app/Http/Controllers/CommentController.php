<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use App\Models\User;
use App\Models\Workout;
use App\Notifications\NewComment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CommentController extends Controller
{
    public function store(Request $request, Workout $workout): RedirectResponse|Response
    {
        $viewer = $request->user();
        $this->ensureVisible($workout, $viewer);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:1000'],
        ]);

        $comment = $workout->comments()->create([
            'user_id' => $viewer->id,
            'body' => $data['body'],
        ]);

        if ($workout->user_id !== $viewer->id) {
            $workout->user->notify(new NewComment($viewer, $comment));
        }

        return $this->respond($request, 'Komentar ditambahkan.');
    }

    public function destroy(Request $request, Comment $comment): RedirectResponse|Response
    {
        $viewer = $request->user();

        $comment->loadMissing('workout.user');

        abort_unless($comment->workout->isVisibleTo($viewer), 404);

        $isAuthor = $comment->user_id === $viewer->id;
        $isOwner = $comment->workout->user_id === $viewer->id;

        abort_unless($isAuthor || $isOwner, 403);

        $comment->delete();

        return $this->respond($request, 'Komentar dihapus.');
    }

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
