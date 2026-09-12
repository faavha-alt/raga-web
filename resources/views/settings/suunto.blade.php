<x-app-layout>
    <x-slot name="header">
        <h1 class="telemetry-value text-4xl sm:text-5xl">{{ __('Suunto') }}</h1>
        <p class="mt-2 text-sm font-medium text-telemetry-slate">Sumber data aktivitas dari Suunto App (read-only, via Suunto Cloud API).</p>
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

            @unless ($configured)
                <x-card>
                    <div class="flex items-center gap-3">
                        <span class="flex h-11 w-11 items-center justify-center border border-telemetry-line bg-telemetry-well text-xl">🔑</span>
                        <div>
                            <p class="font-display font-semibold text-telemetry-ink">Kredensial Suunto belum diisi</p>
                            <p class="text-sm text-telemetry-slate">Isi <span class="telemetry-value text-xs">SUUNTO_CLIENT_ID</span>, <span class="telemetry-value text-xs">SUUNTO_CLIENT_SECRET</span>, dan <span class="telemetry-value text-xs">SUUNTO_SUBSCRIPTION_KEY</span> di <span class="telemetry-value text-xs">.env</span>, lalu jalankan <span class="telemetry-value text-xs">php artisan config:clear</span>.</p>
                        </div>
                    </div>
                    <p class="mt-4 border-t border-telemetry-line pt-4 text-xs text-telemetry-slate">
                        Akses Suunto <span class="font-semibold">tidak diberikan untuk pemakaian pribadi</span> — ajukan
                        <span class="font-semibold">Suunto Partner Program</span> lebih dulu (formulir di
                        <a href="https://www.suunto.com/en-gg/partners/welcome-partners/" target="_blank" rel="noopener" class="font-semibold text-telemetry-chrono-deep hover:underline">suunto.com/partners</a>;
                        centang Suunto Cloud API, tanda tangani agreement, sebutkan email developer). Setelah diterima,
                        langganan <span class="font-semibold">Developer API</span> di
                        <a href="https://apizone.suunto.com/how-to-start" target="_blank" rel="noopener" class="font-semibold text-telemetry-chrono-deep hover:underline">apizone.suunto.com</a>,
                        salin <em>subscription key</em>, lalu isi OAuth settings (app name, client secret, redirect URI
                        <span class="telemetry-value text-xs">{{ \App\Services\Suunto\SuuntoApiClient::redirectUri() }}</span>).
                        Jawaban partner program biasanya ≤ 2 minggu; kontak <span class="telemetry-value text-xs">partners@suunto.com</span>.
                    </p>
                </x-card>
            @endunless

            @if ($connection)
                <x-card>
                    <div class="flex items-center gap-3">
                        <span class="flex h-11 w-11 items-center justify-center border border-telemetry-line bg-telemetry-well text-xl">⌚</span>
                        <div>
                            <p class="font-display font-semibold text-telemetry-ink">Terhubung ke Suunto</p>
                            <p class="text-sm text-telemetry-slate">
                                {{ $connection->suunto_username ? '@'.$connection->suunto_username : 'Akun Suunto' }}
                                · sejak {{ $connection->connected_at?->translatedFormat('d M Y, H:i') ?? '—' }}
                            </p>
                        </div>
                    </div>

                    <div class="mt-5 space-y-1 border-t border-telemetry-line pt-5 text-sm">
                        <p class="text-telemetry-slate">
                            Sync terakhir:
                            <span class="font-semibold text-telemetry-ink">
                                {{ $connection->last_synced_at?->diffForHumans() ?? 'Belum pernah' }}
                            </span>
                        </p>
                        <p class="text-telemetry-slate">
                            Token berlaku sampai:
                            <span class="font-semibold text-telemetry-ink">
                                {{ $connection->expires_at?->translatedFormat('d M Y, H:i') ?? '—' }}
                            </span>
                            <span class="text-xs">(diperbarui otomatis saat sync)</span>
                        </p>
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

                        <form method="POST" action="{{ route('settings.suunto.disconnect') }}" onsubmit="return confirm('Putuskan koneksi Suunto? Kamu perlu otorisasi ulang untuk sync lagi.');">
                            @csrf
                            <x-secondary-button type="submit">Putuskan Koneksi</x-secondary-button>
                        </form>
                    </div>
                </x-card>
            @elseif ($hasCredentials)
                <x-card>
                    <div class="mb-5 flex items-center gap-3">
                        <span class="flex h-11 w-11 items-center justify-center border border-telemetry-line bg-telemetry-well text-xl">🔗</span>
                        <div>
                            <p class="font-display font-semibold text-telemetry-ink">Hubungkan akun Suunto</p>
                            <p class="text-sm text-telemetry-slate">Kamu akan diarahkan ke Suunto untuk memberi izin baca data workout. RAGA tidak menyimpan password Suunto-mu.</p>
                        </div>
                    </div>

                    <a href="{{ route('settings.suunto.connect') }}"
                       class="inline-flex w-full items-center justify-center gap-2 rounded bg-telemetry-ember px-5 py-2.5 font-display text-[11px] font-bold uppercase tracking-[0.08em] text-white transition-colors hover:bg-telemetry-ember-dark">
                        Hubungkan Suunto
                    </a>
                </x-card>
            @endif

            <p class="px-1 text-xs text-telemetry-slate">
                Yang ditarik: workout (jarak, durasi, HR, elevasi, kalori) beserta sampel per detik bila tersedia, lalu
                dipetakan ke tabel aktivitas yang sama dengan Garmin sehingga Recovery, Training, Analytics, dan AI Coach
                otomatis ikut. Suunto Cloud API tidak menyediakan tidur, Body Battery, atau Training Readiness — faktor itu
                dibiarkan kosong, bukan ditebak.
            </p>

        </div>
    </div>
</x-app-layout>
