<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'RAGA') }}</title>

        <!-- PWA Meta & Icons -->
        <meta name="theme-color" content="#F8F9FA">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="default">
        <meta name="apple-mobile-web-app-title" content="RAGA">
        <link rel="manifest" href="/manifest.webmanifest">
        <link rel="icon" type="image/svg+xml" href="/icons/icon.svg">
        <link rel="apple-touch-icon" sizes="180x180" href="/icons/apple-touch-icon.png">

        <!-- Fonts: Inter (prosa) + Space Grotesk (angka & label telemetry). -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700|space-grotesk:400,500,600,700&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-telemetry-ink antialiased">
        <div class="relative flex min-h-screen flex-col items-center justify-center bg-telemetry-canvas px-4 py-10">
            <div class="relative w-full sm:max-w-md">
                <div class="mb-8 flex justify-center">
                    <a href="/" class="inline-flex items-center gap-2">
                        <span class="flex h-12 w-12 items-center justify-center rounded bg-telemetry-ink font-display text-xl font-bold text-white">R</span>
                        <span class="font-display text-2xl font-bold tracking-tight text-telemetry-ink">RAGA</span>
                    </a>
                </div>

                <div class="w-full rounded-lg border border-telemetry-line bg-telemetry-surface px-6 py-8 sm:px-8">
                    {{ $slot }}
                </div>

                <p class="mt-6 text-center font-display text-[10px] font-bold uppercase tracking-[0.12em] text-telemetry-slate">
                    Your Body. Your Data. Your Progress.
                </p>
            </div>
        </div>
    </body>
</html>
