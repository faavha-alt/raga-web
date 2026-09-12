<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ config('app.name', 'RAGA') }} — Your Body. Your Data. Your Progress.</title>

        <!-- PWA Meta & Icons -->
        <meta name="theme-color" content="#F8F9FA">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="default">
        <meta name="apple-mobile-web-app-title" content="RAGA">
        <link rel="manifest" href="/manifest.webmanifest">
        <link rel="icon" type="image/svg+xml" href="/icons/icon.svg">
        <link rel="apple-touch-icon" href="/icons/icon-192x192.png">

        <!-- Fonts: Inter (prosa) + Space Grotesk (angka & label telemetry). -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700|space-grotesk:400,500,600,700&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased bg-telemetry-canvas text-telemetry-ink">
        <div class="relative min-h-screen">
            <!-- Nav -->
            <header class="relative border-b border-telemetry-line">
                <div class="max-w-6xl mx-auto px-6 py-5 flex items-center justify-between">
                    <a href="/" class="inline-flex items-center gap-2">
                        <span class="flex h-9 w-9 items-center justify-center rounded bg-telemetry-ember font-display font-bold text-white">R</span>
                        <span class="font-display text-lg font-bold tracking-tight text-telemetry-ink">RAGA</span>
                    </a>

                    <nav class="flex items-center gap-2">
                        @auth
                            <a href="{{ url('/dashboard') }}" class="inline-flex items-center rounded bg-telemetry-ember px-5 py-2 font-display text-[11px] font-bold uppercase tracking-[0.08em] text-white transition-colors hover:bg-telemetry-ember-dark">
                                Dashboard
                            </a>
                        @else
                            <a href="{{ route('login') }}" class="inline-flex items-center rounded border border-telemetry-line px-4 py-2 font-display text-[11px] font-bold uppercase tracking-[0.08em] text-telemetry-ink transition-colors hover:bg-telemetry-well">
                                Log in
                            </a>
                            @if (Route::has('register'))
                                <a href="{{ route('register') }}" class="inline-flex items-center rounded bg-telemetry-ember px-5 py-2 font-display text-[11px] font-bold uppercase tracking-[0.08em] text-white transition-colors hover:bg-telemetry-ember-dark">
                                    Daftar Gratis
                                </a>
                            @endif
                        @endauth
                    </nav>
                </div>
            </header>

            <!-- Hero -->
            <main class="relative max-w-4xl mx-auto px-6 pt-16 pb-20 text-center">
                <span class="telemetry-label inline-flex items-center gap-2 rounded border border-telemetry-line bg-telemetry-surface px-3 py-1.5 text-telemetry-slate">
                    🔥 Personal Health Intelligence
                </span>

                <h1 class="mt-6 font-display text-4xl sm:text-6xl font-bold tracking-tight leading-[1.05] text-telemetry-ink">
                    Your Body.<br>
                    <span class="text-telemetry-ember">Your Data.</span><br>
                    Your Progress.
                </h1>

                <p class="mt-6 text-lg text-telemetry-slate max-w-xl mx-auto">
                    Satu tempat buat nge-track kesehatan &amp; latihan kamu — dari recovery, detak jantung, sampai training load. Ditenagai data HealthKit &amp; Garmin, bukan tebak-tebakan.
                </p>

                <div class="mt-10 flex flex-col sm:flex-row items-center justify-center gap-3">
                    @auth
                        <a href="{{ url('/dashboard') }}" class="inline-flex items-center justify-center gap-2 rounded bg-telemetry-ember px-8 py-3.5 font-display text-sm font-bold uppercase tracking-[0.08em] text-white transition-colors hover:bg-telemetry-ember-dark">
                            Buka Dashboard →
                        </a>
                    @else
                        @if (Route::has('register'))
                            <a href="{{ route('register') }}" class="inline-flex items-center justify-center gap-2 rounded bg-telemetry-ember px-8 py-3.5 font-display text-sm font-bold uppercase tracking-[0.08em] text-white transition-colors hover:bg-telemetry-ember-dark">
                                Mulai Sekarang, Gratis
                            </a>
                        @endif
                        <a href="{{ route('login') }}" class="inline-flex items-center justify-center gap-2 rounded border border-telemetry-line-strong bg-telemetry-surface px-8 py-3.5 font-display text-sm font-bold uppercase tracking-[0.08em] text-telemetry-ink transition-colors hover:bg-telemetry-well">
                            Sudah punya akun
                        </a>
                    @endauth
                </div>
            </main>

            <!-- Feature bento -->
            <section class="relative max-w-5xl mx-auto px-6 pb-20">
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <x-card>
                        <div class="text-2xl">💪</div>
                        <h3 class="mt-3 font-display font-semibold text-telemetry-ink">Training Load</h3>
                        <p class="mt-1 text-sm text-telemetry-slate">Pantau intensitas latihan biar nggak overtraining atau malah kurang gerak.</p>
                    </x-card>
                    <x-card>
                        <div class="text-2xl">🔋</div>
                        <h3 class="mt-3 font-display font-semibold text-telemetry-ink">Recovery &amp; Readiness</h3>
                        <p class="mt-1 text-sm text-telemetry-slate">Skor recovery harian dari detak jantung istirahat, body battery, stress, dan beban latihan.</p>
                    </x-card>
                    <x-card>
                        <div class="text-2xl">✨</div>
                        <h3 class="mt-3 font-display font-semibold text-telemetry-ink">AI Insight</h3>
                        <p class="mt-1 text-sm text-telemetry-slate">Rekomendasi personal berbasis tren data kamu sendiri, bukan generik.</p>
                    </x-card>
                </div>
            </section>

            <footer class="relative border-t border-telemetry-line">
                <div class="max-w-6xl mx-auto px-6 py-8 text-center text-xs font-medium text-telemetry-slate">
                    RAGA — Phase 1 · Built for real training data.
                </div>
            </footer>
        </div>
    </body>
</html>
