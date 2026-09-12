<?php

namespace App\Http\Controllers;

use App\Models\SuuntoConnection;
use App\Services\Suunto\SuuntoApiClient;
use App\Services\Suunto\SuuntoApiException;
use App\Services\Suunto\SuuntoSyncService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Menghubungkan akun Suunto App lewat OAuth2 (authorization code) dan
 * menjalankan sinkronisasi manual. Berbeda dari Garmin, tidak ada skrip Python:
 * token disimpan terenkripsi di tabel `suunto_connections`.
 */
class SuuntoConnectionController extends Controller
{
    private const STATE_SESSION_KEY = 'suunto_oauth_state';

    public function show(Request $request): View
    {
        $connection = $request->user()->suuntoConnection;

        return view('settings.suunto', [
            'connection' => $connection,
            'configured' => SuuntoApiClient::isConfigured(),
            'hasCredentials' => SuuntoApiClient::hasOAuthCredentials(),
        ]);
    }

    /** Mulai alur OAuth: simpan state di session lalu lempar ke Suunto. */
    public function redirect(Request $request): RedirectResponse
    {
        if (! SuuntoApiClient::hasOAuthCredentials()) {
            return redirect()->route('settings.suunto.show')->withErrors([
                'suunto' => 'SUUNTO_CLIENT_ID / SUUNTO_CLIENT_SECRET belum diisi di .env.',
            ]);
        }

        $state = Str::random(40);
        $request->session()->put(self::STATE_SESSION_KEY, $state);

        try {
            $url = (new SuuntoApiClient)->authorizationUrl($state);
        } catch (SuuntoApiException $e) {
            return redirect()->route('settings.suunto.show')->withErrors(['suunto' => $e->getMessage()]);
        }

        return redirect()->away($url);
    }

    public function callback(Request $request): RedirectResponse
    {
        $expectedState = $request->session()->pull(self::STATE_SESSION_KEY);

        if ($request->filled('error')) {
            return redirect()->route('settings.suunto.show')->withErrors([
                'suunto' => 'Otorisasi Suunto ditolak: '.$request->query('error'),
            ]);
        }

        $state = (string) $request->query('state', '');
        $code = (string) $request->query('code', '');

        if ($code === '' || $expectedState === null || ! hash_equals((string) $expectedState, $state)) {
            return redirect()->route('settings.suunto.show')->withErrors([
                'suunto' => 'Callback Suunto tidak valid (state/kode tidak cocok). Coba hubungkan ulang.',
            ]);
        }

        try {
            $tokens = SuuntoApiClient::exchangeAuthorizationCode($code);
        } catch (SuuntoApiException $e) {
            return redirect()->route('settings.suunto.show')->withErrors(['suunto' => $e->getMessage()]);
        }

        SuuntoConnection::updateOrCreate(
            ['user_id' => $request->user()->id],
            [
                'suunto_username' => $tokens['username'],
                'access_token' => $tokens['access_token'],
                'refresh_token' => $tokens['refresh_token'],
                'expires_at' => $tokens['expires_at'],
                'scope' => $tokens['scope'],
                'connected_at' => now(),
                'last_sync_status' => null,
                'last_sync_message' => null,
            ]
        );

        return redirect()->route('settings.suunto.show')->with('status', 'Berhasil terhubung ke Suunto.');
    }

    public function sync(Request $request, SuuntoSyncService $sync): RedirectResponse
    {
        $user = $request->user();

        if (! $user->suuntoConnection) {
            return redirect()->route('settings.suunto.show')->withErrors(['suunto' => 'Belum terhubung ke Suunto.']);
        }

        $result = $sync->syncForUser($user);

        if ($result['status'] === 'error') {
            return redirect()->route('settings.suunto.show')->withErrors(['suunto' => $result['message'] ?? 'Sinkronisasi gagal.']);
        }

        $message = $result['imported'] === 0
            ? 'Sinkronisasi selesai: tidak ada workout baru.'
            : sprintf('Sinkronisasi selesai: %d workout diimpor.', $result['imported']);

        return redirect()->route('settings.suunto.show')->with('status', $message);
    }

    public function disconnect(Request $request): RedirectResponse
    {
        $request->user()->suuntoConnection?->delete();

        return redirect()->route('settings.suunto.show')->with('status', 'Koneksi Suunto diputus.');
    }
}
