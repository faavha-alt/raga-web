<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Auth\GoogleAccountException;
use App\Services\Auth\GoogleAccountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Throwable;

class GoogleAuthController extends Controller
{
    /**
     * Kirim pengguna ke halaman izin Google.
     *
     * Socialite menyertakan parameter `state` yang terikat ke sesi, jadi
     * permintaan callback yang tidak berasal dari alur ini akan ditolak.
     */
    public function redirect(): RedirectResponse
    {
        return Socialite::driver('google')->redirect();
    }

    /**
     * Terima kembalian dari Google, lalu masuk atau daftarkan pengguna.
     */
    public function callback(Request $request, GoogleAccountService $accounts): RedirectResponse
    {
        // Pengguna menekan "batal" di halaman Google: bukan kesalahan, jadi
        // ditangani sebelum Socialite mencoba menukar kode yang tidak ada.
        if ($request->filled('error')) {
            return $this->backToLogin('Masuk dengan Google dibatalkan.');
        }

        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (InvalidStateException) {
            // Sesi hilang atau tab dibiarkan terbuka terlalu lama. Kejadian biasa.
            return $this->backToLogin('Sesi masuk dengan Google sudah kedaluwarsa. Coba lagi ya.');
        } catch (Throwable $e) {
            report($e);

            return $this->backToLogin('Kami tidak bisa memverifikasi akun Google-mu. Coba masuk sekali lagi.');
        }

        try {
            $user = $accounts->resolve($googleUser);
        } catch (GoogleAccountException $e) {
            return $this->backToLogin($e->getMessage());
        }

        Auth::login($user, remember: true);

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Kembalikan pengguna ke halaman login dengan pesan yang bisa dibaca.
     */
    private function backToLogin(string $message): RedirectResponse
    {
        return redirect()->route('login')->withErrors(['google' => $message]);
    }
}
