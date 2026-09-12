<x-app-layout>
    <x-slot name="header">
        <h1 class="telemetry-value text-4xl sm:text-5xl">{{ __('Suunto') }}</h1>
        <p class="mt-2 text-sm font-medium text-telemetry-slate">Sumber data aktivitas dari Suunto App (read-only).</p>
    </x-slot>

    <div class="py-6 pb-16">
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

            @if ($connection)
                <x-card>
                    <div class="flex items-center gap-3">
                        <span class="flex h-11 w-11 items-center justify-center border border-telemetry-line bg-telemetry-well text-xl">⌚</span>
                        <div class="min-w-0">
                            <p class="font-display font-semibold text-telemetry-ink">Terhubung ke Suunto</p>
                            <p class="truncate text-sm text-telemetry-slate">
                                {{ $connection->suunto_username ?? $connection->email ?? 'Akun Suunto' }}
                                · sejak {{ $connection->connected_at?->translatedFormat('d M Y, H:i') ?? '—' }}
                            </p>
                        </div>
                    </div>

                    <div class="mt-4 flex flex-wrap items-center gap-2 border-t border-telemetry-line pt-4">
                        @if ($connection->auth_mode === 'password')
                            <x-chip variant="pace">mode: suuntool</x-chip>
                            <span class="text-xs text-telemetry-slate">Sesi disimpan lokal di server; password tidak disimpan.</span>
                        @else
                            <x-chip variant="recovery">mode: Cloud API resmi</x-chip>
                        @endif
                    </div>

                    <div class="mt-4 space-y-1 text-sm">
                        <p class="text-telemetry-slate">
                            Sync terakhir:
                            <span class="font-semibold text-telemetry-ink">
                                {{ $connection->last_synced_at?->diffForHumans() ?? 'Belum pernah' }}
                            </span>
                        </p>
                        @if ($connection->auth_mode !== 'password')
                            <p class="text-telemetry-slate">
                                Token berlaku sampai:
                                <span class="font-semibold text-telemetry-ink">
                                    {{ $connection->expires_at?->translatedFormat('d M Y, H:i') ?? '—' }}
                                </span>
                            </p>
                        @endif
                        @if ($connection->last_sync_status === 'error')
                            <p class="font-medium text-telemetry-ember-deep">Sync terakhir gagal: {{ $connection->last_sync_message }}</p>
                        @elseif ($connection->last_sync_message)
                            <p class="text-xs text-telemetry-slate">{{ $connection->last_sync_message }}</p>
                        @endif
                    </div>

                    <div class="mt-5 flex flex-wrap gap-3">
                        <form method="POST" action="{{ route('settings.suunto.sync') }}" x-data="{ loading: false }" @submit="loading = true">
                            @csrf
                            <x-primary-button x-bind:disabled="loading">
                                <span x-show="!loading">🔄 Sync Now</span>
                                <span x-show="loading" x-cloak>Menyinkronkan…</span>
                            </x-primary-button>
                        </form>

                        <form method="POST" action="{{ route('settings.suunto.disconnect') }}" onsubmit="return confirm('Putuskan koneksi Suunto? Kamu perlu login ulang untuk sync lagi.');">
                            @csrf
                            <x-secondary-button type="submit">Putuskan Koneksi</x-secondary-button>
                        </form>
                    </div>
                </x-card>
            @else
                <x-card>
                    <div class="flex items-center gap-3">
                        <span class="flex h-11 w-11 items-center justify-center border border-telemetry-line bg-telemetry-well text-xl">🔗</span>
                        <div>
                            <p class="font-display font-semibold text-telemetry-ink">Hubungkan akun Suunto</p>
                            <p class="text-sm text-telemetry-slate">Login dengan akun Suunto App. Password dipakai sekali untuk membuat sesi — tidak disimpan di database.</p>
                        </div>
                    </div>

                    @if ($toolAvailable)
                        <form method="POST" action="{{ route('settings.suunto.login') }}" class="mt-5 space-y-4">
                            @csrf

                            <div>
                                <x-input-label for="email" value="Email Suunto" />
                                <x-text-input id="email" type="email" name="email" :value="old('email')" required autofocus />
                            </div>

                            <div>
                                <x-input-label for="password" value="Password Suunto" />
                                <x-text-input id="password" type="password" name="password" required autocomplete="off" />
                            </div>

                            <x-primary-button class="w-full">Hubungkan</x-primary-button>
                        </form>

                        <p class="mt-4 border-t border-telemetry-line pt-4 text-xs text-telemetry-slate">
                            Memakai CLI <span class="telemetry-value text-xs">{{ $toolBinary }}</span> (backend aplikasi Suunto).
                            Ini API privat: kontraknya bisa berubah sewaktu-waktu, kuotanya ketat, dan pemakaiannya
                            <span class="font-semibold">berpotensi melanggar ToS Suunto</span> — pakai hanya untuk data akunmu sendiri.
                        </p>
                    @else
                        <div class="mt-5 border-t border-telemetry-line pt-5">
                            <p class="text-sm font-semibold text-telemetry-ember-deep">Binary <span class="telemetry-value text-xs">{{ $toolBinary }}</span> belum terpasang di server.</p>
                            <p class="mt-2 text-xs text-telemetry-slate">
                                Pasang rilis Linux dari
                                <a href="https://github.com/tajchert/suuntool/releases" target="_blank" rel="noopener" class="font-semibold text-telemetry-chrono-deep hover:underline">github.com/tajchert/suuntool/releases</a>
                                ke <span class="telemetry-value text-xs">/usr/local/bin/suuntool</span>, atau set
                                <span class="telemetry-value text-xs">SUUNTO_TOOL_BINARY</span> ke path absolutnya, lalu
                                <span class="telemetry-value text-xs">php artisan config:cache</span>.
                            </p>
                        </div>
                    @endif
                </x-card>

                <x-card>
                    <x-section-heading title="Jalur Resmi // Opsional" hint="Butuh Partner Program" />

                    @if ($hasCredentials)
                        <p class="text-sm text-telemetry-slate">Kredensial Suunto Cloud API sudah diisi — kamu bisa memakai jalur resmi ini.</p>
                        <a href="{{ route('settings.suunto.connect') }}"
                           class="mt-4 inline-flex items-center justify-center gap-2 rounded border border-telemetry-line-strong bg-telemetry-surface px-5 py-2.5 font-display text-[11px] font-bold uppercase tracking-[0.08em] text-telemetry-ink transition-colors hover:border-telemetry-ink hover:bg-telemetry-well">
                            Hubungkan lewat Cloud API
                        </a>
                    @else
                        <p class="text-sm text-telemetry-slate">
                            Suunto Cloud API tidak diberikan untuk pemakaian pribadi — harus lewat
                            <span class="font-semibold">Suunto Partner Program</span>
                            (<a href="https://www.suunto.com/en-gg/partners/welcome-partners/" target="_blank" rel="noopener" class="font-semibold text-telemetry-chrono-deep hover:underline">formulir</a>,
                            agreement, daftar email developer; jawaban ≤ 2 minggu). Jalur resmi juga tidak menyediakan data tidur.
                            Selama belum ada kredensial, pakai mode <span class="font-semibold">suuntool</span> di atas.
                        </p>
                    @endif
                </x-card>
            @endif

            <p class="px-1 text-xs text-telemetry-slate">
                Yang ditarik: workout (jarak, durasi, HR, elevasi, kalori, sampel per detik) dan tidur, lalu dipetakan ke
                tabel yang sama dengan Garmin sehingga Recovery, Training, Analytics, dan AI Coach otomatis ikut.
                Faktor yang tidak tersedia (Body Battery, Training Readiness, stress) dibiarkan kosong — bukan ditebak.
            </p>

        </div>
    </div>
</x-app-layout>
