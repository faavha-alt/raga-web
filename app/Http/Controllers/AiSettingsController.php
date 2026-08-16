<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AiSettingsController extends Controller
{
    public function show(Request $request): View
    {
        return view('settings.ai', [
            'setting' => $request->user()->aiSetting,
            'providers' => config('ai.providers'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        $existing = $user->aiSetting;

        $data = $request->validate([
            'provider' => ['required', 'string', 'in:'.implode(',', array_keys(config('ai.providers')))],
            'api_key' => [$existing ? 'nullable' : 'required', 'string'],
            'model' => ['nullable', 'string', 'max:255'],
        ]);

        $attributes = [
            'provider' => $data['provider'],
            'model' => $data['model'] ?: null,
        ];

        if (filled($data['api_key'] ?? null)) {
            $attributes['api_key'] = $data['api_key'];
        }

        $user->aiSetting()->updateOrCreate(['user_id' => $user->id], $attributes);

        return redirect()->route('settings.ai.show')->with('status', 'Pengaturan AI Coach disimpan.');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->user()->aiSetting?->delete();

        return redirect()->route('settings.ai.show')->with('status', 'API key AI Coach dihapus.');
    }
}
