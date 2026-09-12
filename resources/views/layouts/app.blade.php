<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'RAGA') }}</title>

        <!-- PWA Meta & Icons -->
        <meta name="theme-color" content="#090d16">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
        <meta name="apple-mobile-web-app-title" content="RAGA">
        <link rel="manifest" href="/manifest.webmanifest">
        <link rel="icon" type="image/svg+xml" href="/icons/icon.svg">
        <link rel="apple-touch-icon" href="/icons/icon-192x192.png">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:500,600,700,800&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])

        <!-- Registrasi service worker + tombol "Pasang aplikasi".
             (Tag manifest/theme-color/ikon sudah ditulis di <head> di atas.) -->
        @include('partials.pwa')

        <!-- Meta tambahan per halaman (mis. manifest PWA & theme-color). -->
        @stack('head')
    </head>
    <body class="font-sans antialiased">
        <div x-data="{ sidebarOpen: false }" class="min-h-screen bg-gray-50 dark:bg-gray-950 bg-mesh lg:flex">
            @include('layouts.navigation')

            <!-- Main column -->
            <div class="min-w-0 flex-1">
                <!-- Page Heading -->
                @isset($header)
                    <header>
                        <div class="px-4 pb-2 pt-8 sm:px-6 lg:px-8">
                            {{ $header }}
                        </div>
                    </header>
                @endisset

                <!-- Page Content -->
                <main>
                    {{ $slot }}
                </main>
            </div>
        </div>

        <!-- Registrasi service worker + tombol "Pasang aplikasi". -->
        @include('partials.pwa')

        <!-- Skrip khusus halaman (mis. Alpine component perekam GPS). -->
        @stack('scripts')
    </body>
</html>
