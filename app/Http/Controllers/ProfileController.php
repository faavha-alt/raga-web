<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Support\AvatarStorage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request, AvatarStorage $avatars): RedirectResponse
    {
        $user = $request->user();

        $user->fill($request->safe()->except(['avatar']));

        // Checkbox tidak mengirim apa pun saat tidak dicentang, jadi nilainya
        // harus ditetapkan eksplisit agar bisa dimatikan.
        $user->is_public = $request->boolean('is_public');

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        if ($request->hasFile('avatar')) {
            $avatars->delete($user->avatar_path);
            $user->avatar_path = $avatars->store($request->file('avatar'));
        }

        $user->save();

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        // Pengguna yang mendaftar lewat Google tidak punya password untuk
        // dikonfirmasi; tanpa pengecualian ini mereka tidak akan pernah bisa
        // menghapus akunnya sendiri.
        if ($request->user()->hasPassword()) {
            $request->validateWithBag('userDeletion', [
                'password' => ['required', 'current_password'],
            ]);
        }

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
