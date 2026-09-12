<?php

namespace App\Http\Controllers;

use App\Models\SuuntoConnection;
use App\Services\Suunto\SuuntoApiClient;
use App\Services\Suunto\SuuntoApiException;
use App\Services\Suunto\SuuntoSyncService;
use App\Services\Suunto\SuuntoToolClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
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
            'toolAvailable' => SuuntoToolClient::isAvailable(),
            'toolBinary' => SuuntoToolClient::binary(),
        ]);
    }

    /**
     * Jalur tidak resmi: login email/password Suunto lewat CLI `suuntool`.
     * Password tidak disimpan — hanya diteruskan ke proses login sekali.
     */
    public function loginWithTool(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! SuuntoToolClient::isAvailable()) {
            return redirect()->route('settings.suunto.show')->withErrors([
                'suunto' => 'Binary `'.SuuntoToolClient::binary().'` tidak ditemukan di server. Pasang suuntool dulu (lihat README).',
            ]);
        }

        $result = (new SuuntoToolClient($request->user()))->login($data['email'], $data['password']);

        if ($result['status'] === 'error') {
            return redirect()->route('settings.suunto.show')->withErrors([
                'suunto' => 'Login Suunto gagal: '.($result['message'] ?? 'tidak diketahui'),
            ]);
        }

        SuuntoConnection::updateOrCreate(
            ['user_id' => $request->user()->id],
            [
                'auth_mode' => SuuntoToolClient::authMode(),
                'email' => $data['email'],
                'suunto_username' => $result['username'],
                'access_token' => null,
                'refresh_token' => null,
                'expires_at' => null,
                'scope' => null,
                'connected_at' => now(),
                'last_sync_status' => null,
                'last_sync_message' => null,
            ]
        );

        return redirect()->route('settings.suunto.show')->with('status', 'Terhubung ke Suunto (mode suuntool). Jalankan Sync Now untuk menarik data.');
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
        $user = $request->user();

        $user->suuntoConnection?->delete();

        // Sesi suuntool hidup di berkas terpisah — hapus juga supaya benar-benar putus.
        $sessionDirectory = dirname(SuuntoToolClient::sessionPathForUser($user));

        if (is_dir($sessionDirectory)) {
            File::deleteDirectory($sessionDirectory);
        }

        return redirect()->route('settings.suunto.show')->with('status', 'Koneksi Suunto diputus.');
    }
}
