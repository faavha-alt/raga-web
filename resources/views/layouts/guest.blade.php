<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'RAGA') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:500,600,700,800&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-gray-900 antialiased">
        <div class="relative min-h-screen overflow-hidden bg-gray-950 flex flex-col justify-center items-center px-4 py-10">
            <!-- Ambient gradient blobs -->
            <div class="pointer-events-none absolute -top-32 -left-32 h-96 w-96 rounded-full bg-raga-accent/30 blur-3xl"></div>
            <div class="pointer-events-none absolute -bottom-32 -right-24 h-96 w-96 rounded-full bg-raga-primary/30 blur-3xl"></div>
            <div class="pointer-events-none absolute top-1/3 right-1/4 h-64 w-64 rounded-full bg-raga-energy/20 blur-3xl"></div>

            <div class="relative w-full sm:max-w-md">
                <div class="mb-8 flex justify-center">
                    <a href="/" class="inline-flex items-center gap-2">
                        <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-raga-accent to-raga-primary text-white font-black text-xl shadow-glow">R</span>
                        <span class="text-2xl font-extrabold tracking-tight text-white">RAGA</span>
                    </a>
                </div>

                <div class="w-full rounded-3xl border border-white/10 bg-white/95 backdrop-blur-xl px-6 py-8 sm:px-8 shadow-2xl">
                    {{ $slot }}
                </div>

                <p class="mt-6 text-center text-sm font-medium text-white/40">
                    Your Body. Your Data. Your Progress.
                </p>
            </div>
        </div>
    </body>
</html>
