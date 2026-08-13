<?php

namespace App\Http\Controllers;

use App\Models\GarminConnection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Process;
use Illuminate\View\View;

class GarminConnectionController extends Controller
{
    private const PYTHON = '/usr/bin/python3';

    public function show(Request $request): View
    {
        $connection = $request->user()->garminConnection;

        return view('settings.garmin', [
            'connection' => $connection,
            'needsMfa' => (bool) session('garmin_needs_mfa'),
        ]);
    }

    public function connect(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'mfa_code' => ['nullable', 'string'],
        ]);

        $result = Process::path(base_path())
            ->timeout(60)
            ->input(json_encode([
                'email' => $data['email'],
                'password' => $data['password'],
                'mfa_code' => $data['mfa_code'] ?? null,
            ]))
            ->run([self::PYTHON, 'scripts/garmin_login.py']);

        $response = json_decode($result->output(), true);

        if (! is_array($response)) {
            return back()->withErrors(['email' => 'Login gagal: tidak ada respons dari Garmin ('.trim($result->errorOutput()).')']);
        }

        if ($response['status'] === 'mfa_required') {
            return back()->with('garmin_needs_mfa', true)->withInput($request->only('email'));
        }

        if ($response['status'] === 'error') {
            return back()->withErrors(['email' => 'Login Garmin gagal: '.($response['message'] ?? 'unknown error')]);
        }

        GarminConnection::updateOrCreate(
            ['user_id' => $request->user()->id],
            ['connected_at' => now()]
        );

        return redirect()->route('settings.garmin.show')->with('status', 'Berhasil terhubung ke Garmin Connect.');
    }

    public function sync(Request $request): RedirectResponse
    {
        $user = $request->user();
        $connection = $user->garminConnection;

        if (! $connection) {
            return redirect()->route('settings.garmin.show')->withErrors(['sync' => 'Belum terhubung ke Garmin.']);
        }

        $result = Process::path(base_path())
            ->timeout(120)
            ->run([self::PYTHON, 'scripts/garmin_sync.py', '--days', '3']);

        if ($result->failed()) {
            $connection->update([
                'last_synced_at' => now(),
                'last_sync_status' => 'error',
                'last_sync_message' => trim($result->errorOutput()) ?: 'Gagal menjalankan sinkronisasi.',
            ]);

            return redirect()->route('settings.garmin.show')->withErrors(['sync' => 'Sinkronisasi gagal, cek pesan error di bawah.']);
        }

        $tmpFile = tempnam(sys_get_temp_dir(), 'garmin_sync_');
        file_put_contents($tmpFile, $result->output());

        try {
            Artisan::call('garmin:import', ['path' => $tmpFile, '--user' => $user->id]);
        } finally {
            @unlink($tmpFile);
        }

        $connection->update([
            'last_synced_at' => now(),
            'last_sync_status' => 'success',
            'last_sync_message' => null,
        ]);

        return redirect()->route('settings.garmin.show')->with('status', 'Sinkronisasi selesai.');
    }

    public function disconnect(Request $request): RedirectResponse
    {
        $tokenPath = getenv('HOME').'/.garmin_tokens';

        if (is_dir($tokenPath)) {
            Process::run(['rm', '-rf', $tokenPath]);
        }

        $request->user()->garminConnection?->delete();

        return redirect()->route('settings.garmin.show')->with('status', 'Koneksi Garmin diputus.');
    }
}
