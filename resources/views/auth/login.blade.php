<x-guest-layout>
    <div class="mb-6 border-b border-telemetry-line pb-5">
        <h1 class="telemetry-value text-2xl text-telemetry-ink">Selamat datang balik 👋</h1>
        <p class="mt-1 text-sm text-telemetry-slate">Masuk untuk lanjut pantau progress kamu.</p>
    </div>

    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <div class="mb-6">
        <x-google-button />
    </div>

    <form method="POST" action="{{ route('login') }}" class="space-y-5">
        @csrf

        <!-- Email Address -->
        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <!-- Password -->
        <div>
            <x-input-label for="password" :value="__('Password')" />

            <x-text-input id="password"
                            type="password"
                            name="password"
                            required autocomplete="current-password" />

            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <!-- Remember Me -->
        <div class="flex items-center justify-between">
            <label for="remember_me" class="inline-flex items-center">
                <input id="remember_me" type="checkbox" class="rounded border-telemetry-line text-telemetry-ink focus:ring-telemetry-ink" name="remember">
                <span class="ms-2 text-sm text-telemetry-slate">{{ __('Remember me') }}</span>
            </label>

            @if (Route::has('password.request'))
                <a class="inline-flex items-center min-h-11 sm:min-h-0 font-display text-[11px] font-bold uppercase tracking-[0.08em] text-telemetry-ember-deep hover:underline" href="{{ route('password.request') }}">
                    {{ __('Forgot password?') }}
                </a>
            @endif
        </div>

        <x-primary-button class="w-full">
            {{ __('Log in') }}
        </x-primary-button>

        @if (Route::has('register'))
            <p class="text-center text-sm text-telemetry-slate">
                Belum punya akun?
                <a href="{{ route('register') }}" class="inline-flex items-center min-h-11 sm:min-h-0 font-semibold text-telemetry-ember-deep hover:underline">Daftar sekarang</a>
            </p>
        @endif
    </form>
</x-guest-layout>
