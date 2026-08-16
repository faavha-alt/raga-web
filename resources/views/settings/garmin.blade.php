<x-app-layout>
    <x-slot name="header">
        <h2 class="text-2xl font-extrabold text-gray-900 dark:text-white leading-tight">
            {{ __('Garmin Connect') }}
        </h2>
    </x-slot>

    <div class="py-6 pb-16">
        <div class="px-4 sm:px-6 lg:px-8 space-y-4">

            @if (session('status'))
                <div class="rounded-2xl bg-raga-excellent/10 border border-raga-excellent/20 px-4 py-3 text-sm font-semibold text-raga-excellent">
                    {{ session('status') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="rounded-2xl bg-raga-low/10 border border-raga-low/20 px-4 py-3 text-sm font-semibold text-raga-low">
                    {{ $errors->first() }}
                </div>
            @endif

            @if ($connection && $connection->connected_at)
                <x-card>
                    <div class="flex items-center gap-3">
                        <span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-raga-excellent/10 text-xl">✅</span>
                        <div>
                            <p class="font-bold text-gray-900 dark:text-white">Terhubung ke Garmin Connect</p>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Sejak {{ $connection->connected_at->translatedFormat('d M Y, H:i') }}</p>
                        </div>
                    </div>

                    <div class="mt-5 pt-5 border-t border-gray-100 dark:border-gray-700 space-y-1 text-sm">
                        <p class="text-gray-500 dark:text-gray-400">
                            Sync terakhir:
                            <span class="font-semibold text-gray-700 dark:text-gray-200">
                                {{ $connection->last_synced_at?->diffForHumans() ?? 'Belum pernah' }}
                            </span>
                        </p>
                        @if ($connection->last_sync_status === 'error')
                            <p class="text-raga-low font-medium">Sync terakhir gagal: {{ $connection->last_sync_message }}</p>
                        @endif
                    </div>

                    <div class="mt-5 flex flex-wrap gap-3">
                        <form method="POST" action="{{ route('settings.garmin.sync') }}" x-data="{ loading: false }" @submit="loading = true">
                            @csrf
                            <x-primary-button x-bind:disabled="loading">
                                <span x-show="!loading">🔄 Sync Now</span>
                                <span x-show="loading" x-cloak>Menyinkronkan…</span>
                            </x-primary-button>
                        </form>

                        <form method="POST" action="{{ route('settings.garmin.disconnect') }}" onsubmit="return confirm('Putuskan koneksi Garmin? Kamu perlu login ulang untuk sync lagi.');">
                            @csrf
                            <x-secondary-button type="submit">Putuskan Koneksi</x-secondary-button>
                        </form>
                    </div>
                </x-card>
            @else
                <x-card>
                    <div class="flex items-center gap-3 mb-5">
                        <span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-raga-primary/10 text-xl">⌚</span>
                        <div>
                            <p class="font-bold text-gray-900 dark:text-white">Hubungkan Garmin Connect</p>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Login pakai akun Garmin kamu. Kredensial tidak disimpan — hanya dipakai sekali untuk ambil token.</p>
                        </div>
                    </div>

                    <form method="POST" action="{{ route('settings.garmin.connect') }}" class="space-y-4">
                        @csrf

                        <div>
                            <x-input-label for="email" value="Email Garmin" />
                            <x-text-input id="email" type="email" name="email" :value="old('email')" required autofocus />
                            <x-input-error :messages="$errors->get('email')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="password" value="Password Garmin" />
                            <x-text-input id="password" type="password" name="password" required autocomplete="off" />
                        </div>

                        @if ($needsMfa)
                            <div>
                                <x-input-label for="mfa_code" value="Kode MFA" />
                                <x-text-input id="mfa_code" type="text" name="mfa_code" inputmode="numeric" autocomplete="one-time-code" placeholder="Kode dari authenticator/email" required autofocus />
                                <p class="mt-1.5 text-xs text-gray-400">Akun kamu pakai verifikasi 2 langkah — masukkan kodenya, lalu submit lagi bareng email &amp; password.</p>
                            </div>
                        @endif

                        <x-primary-button class="w-full">Hubungkan</x-primary-button>
                    </form>
                </x-card>
            @endif

        </div>
    </div>
</x-app-layout>
