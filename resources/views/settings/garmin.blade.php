<x-app-layout>
    <x-slot name="header">
        <h1 class="telemetry-value text-2xl sm:text-3xl lg:text-5xl">{{ __('Garmin Connect') }}</h1>
        <p class="mt-1 text-sm font-medium text-telemetry-slate">Sumber data aktivitas, biometrik, dan tidurmu.</p>
    </x-slot>

    <div class="py-4 sm:py-6 pb-16">
        <div class="px-4 sm:px-6 lg:px-8 max-w-xl space-y-4">

            @if (session('status'))
                <div class="rounded border border-telemetry-emerald/20 bg-telemetry-emerald/10 px-4 py-3 text-sm font-semibold text-telemetry-emerald-deep">
                    {{ session('status') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="rounded border border-telemetry-ember/25 bg-telemetry-ember/10 px-4 py-3 text-sm font-semibold text-telemetry-ember-deep">
                    {{ $errors->first() }}
                </div>
            @endif

            @if ($connection && $connection->connected_at)
                <x-card>
                    <div class="flex items-center gap-3">
                        <span class="flex h-11 w-11 items-center justify-center border border-telemetry-line bg-telemetry-well text-xl">✅</span>
                        <div>
                            <p class="font-display font-semibold text-telemetry-ink">Terhubung ke Garmin Connect</p>
                            <p class="text-sm text-telemetry-slate">Sejak {{ $connection->connected_at->translatedFormat('d M Y, H:i') }}</p>
                        </div>
                    </div>

                    <div class="mt-5 pt-5 border-t border-telemetry-line space-y-1 text-sm">
                        <p class="text-telemetry-slate">
                            Sync terakhir:
                            <span class="font-semibold text-telemetry-ink">
                                {{ $connection->last_synced_at?->diffForHumans() ?? 'Belum pernah' }}
                            </span>
                        </p>
                        @if ($connection->last_sync_status === 'error')
                            <p class="font-medium text-telemetry-ember-deep">Sync terakhir gagal: {{ $connection->last_sync_message }}</p>
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
                        <span class="flex h-11 w-11 items-center justify-center border border-telemetry-line bg-telemetry-well text-xl">⌚</span>
                        <div>
                            <p class="font-display font-semibold text-telemetry-ink">Hubungkan Garmin Connect</p>
                            <p class="text-sm text-telemetry-slate">Login pakai akun Garmin kamu. Kredensial tidak disimpan — hanya dipakai sekali untuk ambil token.</p>
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
                                <p class="mt-1.5 text-xs text-telemetry-slate">Akun kamu pakai verifikasi 2 langkah — masukkan kodenya, lalu submit lagi bareng email &amp; password.</p>
                            </div>
                        @endif

                        <x-primary-button class="w-full">Hubungkan</x-primary-button>
                    </form>
                </x-card>
            @endif

            @if ($connection && $connection->connected_at)
                <x-card>
                    <x-section-heading title="Backfill Riwayat // CLI" hint="1–2 tahun" />
                    <p class="text-sm text-telemetry-slate">
                        Tombol <span class="font-semibold">Sync Now</span> menarik 2 hari terakhir. Untuk menarik
                        <span class="font-semibold">1–2 tahun</span> (dipotong per 60 hari agar tidak timeout), jalankan dari
                        terminal server:
                    </p>
                    <pre class="mt-3 overflow-x-auto rounded border border-telemetry-line bg-telemetry-well px-3 py-2 font-display text-[11px] leading-relaxed text-telemetry-ink">php artisan garmin:sync --days=730 --chunk=60</pre>
                    <p class="mt-2 text-xs text-telemetry-slate">
                        Progres ditampilkan per chunk (terbaru dulu) dan aman diulang — chunk yang sudah masuk akan dilewati.
                    </p>
                </x-card>
            @endif

        </div>
    </div>
</x-app-layout>
