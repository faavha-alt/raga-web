<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Manage a user's static API tokens (Sanctum personal access tokens). These
 * authenticate the token-based REST API (/api/*) and the remote MCP endpoint
 * (POST /mcp) without the OAuth browser flow — paste one into an MCP client
 * as a bearer token.
 */
class ApiTokenController extends Controller
{
    public function show(Request $request): View
    {
        return view('settings.api-tokens', [
            'tokens' => $request->user()->tokens()->latest()->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $token = $request->user()->createToken($data['name']);

        return back()
            ->with('status', 'Token dibuat. Salin sekarang — tidak akan ditampilkan lagi.')
            ->with('plain_text_token', $token->plainTextToken);
    }

    public function destroy(Request $request, int $token): RedirectResponse
    {
        $request->user()->tokens()->whereKey($token)->delete();

        return back()->with('status', 'Token dicabut.');
    }
}
