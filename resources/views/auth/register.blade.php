<x-guest-layout>
    <div class="mb-6">
        <h1 class="text-2xl font-extrabold text-gray-900">Gas mulai, buat akun 🚀</h1>
        <p class="mt-1 text-sm text-gray-500">Gratis, cepat, dan datamu tetap punya kamu sendiri.</p>
    </div>

    <form method="POST" action="{{ route('register') }}" class="space-y-5">
        @csrf

        <!-- Name -->
        <div>
            <x-input-label for="name" :value="__('Name')" />
            <x-text-input id="name" type="text" name="name" :value="old('name')" required autofocus autocomplete="name" />
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>

        <!-- Username: dipakai sebagai alamat profil publik (/@username) -->
        <div>
            <x-input-label for="username" value="Username" />
            <div class="relative">
                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-sm font-semibold text-gray-400">@</span>
                <x-text-input id="username" type="text" name="username" class="pl-8" :value="old('username')" required autocomplete="username" inputmode="latin" />
            </div>
            <p class="mt-1.5 text-xs text-gray-500">Ini alamat profil publikmu: <span class="font-semibold text-raga-primary">raga.favha.cloud/@username</span>. Huruf kecil, angka, titik, garis bawah, atau tanda hubung.</p>
            <x-input-error :messages="$errors->get('username')" class="mt-2" />
        </div>

        <!-- Email Address -->
        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" type="email" name="email" :value="old('email')" required autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <!-- Password -->
        <div>
            <x-input-label for="password" :value="__('Password')" />

            <x-text-input id="password"
                            type="password"
                            name="password"
                            required autocomplete="new-password" />

            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <!-- Confirm Password -->
        <div>
            <x-input-label for="password_confirmation" :value="__('Confirm Password')" />

            <x-text-input id="password_confirmation"
                            type="password"
                            name="password_confirmation" required autocomplete="new-password" />

            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
        </div>

        <x-primary-button class="w-full">
            {{ __('Register') }}
        </x-primary-button>

        <p class="text-center text-sm text-gray-500">
            Sudah punya akun?
            <a href="{{ route('login') }}" class="font-semibold text-raga-primary hover:text-raga-accent">Masuk di sini</a>
        </p>
    </form>
</x-guest-layout>
