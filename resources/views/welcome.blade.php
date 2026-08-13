<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ config('app.name', 'RAGA') }} — Your Body. Your Data. Your Progress.</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:500,600,700,800,900&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased bg-gray-950 text-white">
        <div class="relative min-h-screen overflow-hidden">
            <!-- Ambient gradient blobs -->
            <div class="pointer-events-none absolute -top-40 -left-40 h-96 w-96 rounded-full bg-raga-accent/30 blur-3xl"></div>
            <div class="pointer-events-none absolute top-1/4 -right-32 h-96 w-96 rounded-full bg-raga-primary/30 blur-3xl"></div>
            <div class="pointer-events-none absolute bottom-0 left-1/3 h-80 w-80 rounded-full bg-raga-energy/20 blur-3xl"></div>

            <!-- Nav -->
            <header class="relative max-w-6xl mx-auto px-6 py-6 flex items-center justify-between">
                <a href="/" class="inline-flex items-center gap-2">
                    <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-raga-accent to-raga-primary text-white font-black shadow-glow">R</span>
                    <span class="text-lg font-extrabold tracking-tight">RAGA</span>
                </a>

                <nav class="flex items-center gap-2">
                    @auth
                        <a href="{{ url('/dashboard') }}" class="inline-flex items-center rounded-full bg-gradient-to-r from-raga-accent to-raga-primary px-5 py-2 text-sm font-bold shadow-glow hover:brightness-110 transition">
                            Dashboard
                        </a>
                    @else
                        <a href="{{ route('login') }}" class="inline-flex items-center rounded-full px-4 py-2 text-sm font-semibold text-white/80 hover:text-white transition">
                            Log in
                        </a>
                        @if (Route::has('register'))
                            <a href="{{ route('register') }}" class="inline-flex items-center rounded-full bg-gradient-to-r from-raga-accent to-raga-primary px-5 py-2 text-sm font-bold shadow-glow hover:brightness-110 transition">
                                Daftar Gratis
                            </a>
                        @endif
                    @endauth
                </nav>
            </header>

            <!-- Hero -->
            <main class="relative max-w-4xl mx-auto px-6 pt-16 pb-24 text-center">
                <span class="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-4 py-1.5 text-xs font-bold uppercase tracking-wider text-white/60">
                    🔥 Personal Health Intelligence
                </span>

                <h1 class="mt-6 text-4xl sm:text-6xl font-black tracking-tight leading-[1.05]">
                    Your Body.<br>
                    <span class="bg-gradient-to-r from-raga-accent via-raga-primary to-raga-energy bg-clip-text text-transparent">Your Data.</span><br>
                    Your Progress.
                </h1>

                <p class="mt-6 text-lg text-white/60 max-w-xl mx-auto">
                    Satu tempat buat nge-track kesehatan &amp; latihan kamu — dari sleep, recovery, sampai training load. Ditenagai data HealthKit &amp; Garmin, bukan tebak-tebakan.
                </p>

                <div class="mt-10 flex flex-col sm:flex-row items-center justify-center gap-3">
                    @auth
                        <a href="{{ url('/dashboard') }}" class="inline-flex items-center justify-center gap-2 rounded-full bg-gradient-to-r from-raga-accent to-raga-primary px-8 py-3.5 text-base font-bold shadow-glow hover:brightness-110 active:scale-[0.98] transition">
                            Buka Dashboard →
                        </a>
                    @else
                        @if (Route::has('register'))
                            <a href="{{ route('register') }}" class="inline-flex items-center justify-center gap-2 rounded-full bg-gradient-to-r from-raga-accent to-raga-primary px-8 py-3.5 text-base font-bold shadow-glow hover:brightness-110 active:scale-[0.98] transition">
                                Mulai Sekarang, Gratis
                            </a>
                        @endif
                        <a href="{{ route('login') }}" class="inline-flex items-center justify-center gap-2 rounded-full border-2 border-white/15 px-8 py-3.5 text-base font-bold text-white hover:border-white/40 transition">
                            Sudah punya akun
                        </a>
                    @endauth
                </div>
            </main>

            <!-- Feature bento -->
            <section class="relative max-w-5xl mx-auto px-6 pb-24">
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div class="rounded-3xl border border-white/10 bg-white/5 backdrop-blur p-6">
                        <div class="text-2xl">💪</div>
                        <h3 class="mt-3 font-bold text-white">Training Load</h3>
                        <p class="mt-1 text-sm text-white/50">Pantau intensitas latihan biar nggak overtraining atau malah kurang gerak.</p>
                    </div>
                    <div class="rounded-3xl border border-white/10 bg-white/5 backdrop-blur p-6">
                        <div class="text-2xl">🌙</div>
                        <h3 class="mt-3 font-bold text-white">Sleep &amp; Recovery</h3>
                        <p class="mt-1 text-sm text-white/50">Skor recovery harian dari HRV, sleep, dan detak jantung istirahat.</p>
                    </div>
                    <div class="rounded-3xl border border-white/10 bg-white/5 backdrop-blur p-6">
                        <div class="text-2xl">✨</div>
                        <h3 class="mt-3 font-bold text-white">AI Insight</h3>
                        <p class="mt-1 text-sm text-white/50">Rekomendasi personal berbasis tren data kamu sendiri, bukan generik.</p>
                    </div>
                </div>
            </section>

            <footer class="relative max-w-6xl mx-auto px-6 pb-10 text-center text-xs font-medium text-white/30">
                RAGA — Phase 1 · Built for real training data.
            </footer>
        </div>
    </body>
</html>
