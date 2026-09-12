<?php

namespace App\Http\Controllers;

use App\Models\Workout;
use App\Support\ActivityVisibility;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ActivityVisibilityController extends Controller
{
    public function update(Request $request, Workout $workout): RedirectResponse
    {
        // Non-pemilik -> 404 (bukan 403) supaya keberadaan aktivitas orang lain
        // tidak terkonfirmasi lewat endpoint ini.
        if ($workout->user_id !== $request->user()->id) {
            throw new NotFoundHttpException;
        }

        $data = $request->validate([
            'visibility' => ['required', 'string', Rule::in(ActivityVisibility::values())],
        ]);

        $workout->update(['visibility' => $data['visibility']]);

        return back()->with('status', 'Visibilitas aktivitas diperbarui.');
    }
}
