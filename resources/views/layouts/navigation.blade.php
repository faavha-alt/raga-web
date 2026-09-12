@php
    // Navigasi utama (baris atas) — modul yang paling sering dibuka.
    $primaryNav = [
        ['route' => 'dashboard', 'active' => 'dashboard', 'label' => 'Dashboard'],
        ['route' => 'feed', 'active' => 'feed', 'label' => 'Feed'],
        ['route' => 'training', 'active' => 'training*', 'label' => 'Training'],
        ['route' => 'health', 'active' => 'health*', 'label' => 'Health'],
        ['route' => 'analytics', 'active' => 'analytics*', 'label' => 'Analytics'],
        ['route' => 'ai', 'active' => 'ai', 'label' => 'AI'],
    ];

    // Strip sekunder — modul analitik & penjelajahan.
    $secondaryNav = [
        ['route' => 'explore', 'active' => 'explore', 'icon' => '🔍', 'label' => 'Explore'],
        ['route' => 'segments.index', 'active' => 'segments*', 'icon' => '🏔️', 'label' => 'Segment'],
        ['route' => 'goals.index', 'active' => 'goals*', 'icon' => '🎯', 'label' => 'Goals'],
        ['route' => 'running', 'active' => 'running*', 'icon' => '🏃', 'label' => 'Running'],
        ['route' => 'trail', 'active' => 'trail*', 'icon' => '⛰️', 'label' => 'Trail'],
        ['route' => 'recovery', 'active' => 'recovery', 'icon' => '🔋', 'label' => 'Recovery'],
        ['route' => 'athlete.dna', 'active' => 'athlete.dna', 'icon' => '🧬', 'label' => 'Athlete DNA'],
        ['route' => 'settings', 'active' => 'settings', 'icon' => '⚙️', 'label' => 'Settings'],
    ];

    // Item baru muncul hanya setelah rutenya terdaftar, supaya halaman lama
    // tidak rusak bila salah satu modul dinonaktifkan.
    $keepRegistered = static fn (array $item): bool => \Illuminate\Support\Facades\Route::has($item['route']);
    $primaryNav = array_values(array_filter($primaryNav, $keepRegistered));
    $secondaryNav = array_values(array_filter($secondaryNav, $keepRegistered));

    // Item untuk panel mobile: seluruh modul, dengan ikon.
    $mobileNav = [
        ['route' => 'dashboard', 'active' => 'dashboard', 'icon' => '🏠', 'label' => 'Dashboard'],
        ['route' => 'feed', 'active' => 'feed', 'icon' => '📰', 'label' => 'Feed'],
        ['route' => 'record.index', 'active' => 'record*', 'icon' => '⏺️', 'label' => 'Rekam'],
        ['route' => 'explore', 'active' => 'explore', 'icon' => '🔍', 'label' => 'Explore'],
        ['route' => 'segments.index', 'active' => 'segments*', 'icon' => '🏔️', 'label' => 'Segment'],
        ['route' => 'training', 'active' => 'training*', 'icon' => '🏋️', 'label' => 'Training'],
        ['route' => 'goals.index', 'active' => 'goals*', 'icon' => '🎯', 'label' => 'Goals'],
        ['route' => 'running', 'active' => 'running*', 'icon' => '🏃', 'label' => 'Running'],
        ['route' => 'trail', 'active' => 'trail*', 'icon' => '⛰️', 'label' => 'Trail'],
        ['route' => 'health', 'active' => 'health*', 'icon' => '❤️', 'label' => 'Health'],
        ['route' => 'recovery', 'active' => 'recovery', 'icon' => '🔋', 'label' => 'Recovery'],
        ['route' => 'athlete.dna', 'active' => 'athlete.dna', 'icon' => '🧬', 'label' => 'Athlete DNA'],
        ['route' => 'analytics', 'active' => 'analytics*', 'icon' => '📊', 'label' => 'Analytics'],
        ['route' => 'ai', 'active' => 'ai', 'icon' => '🤖', 'label' => 'AI'],
        ['route' => 'settings', 'active' => 'settings', 'icon' => '⚙️', 'label' => 'Settings'],
    ];
    $mobileNav = array_values(array_filter($mobileNav, $keepRegistered));
@endphp

<header
    x-data="{ mobileOpen: false, userMenu: false }"
    class="sticky top-0 z-40 border-b border-telemetry-line bg-white/95 backdrop-blur-xl"
>
    {{-- Baris utama --}}
    <div class="flex h-16 items-center justify-between gap-4 px-4 sm:px-6 lg:px-8">
        <a href="{{ route('dashboard') }}" class="shrink-0">
            <x-application-logo />
        </a>

        <nav class="hidden items-center gap-0.5 lg:flex">
            @foreach ($primaryNav as $item)
                <x-topnav-link :href="route($item['route'])" :active="request()->routeIs($item['active'])">
                    {{ __($item['label']) }}
                </x-topnav-link>
            @endforeach
        </nav>

        <div class="flex items-center gap-2">
            @if (Route::has('record.index'))
                <a
                    href="{{ route('record.index') }}"
                    class="hidden items-center gap-1.5 bg-telemetry-ember px-3 py-2 font-display text-[11px] font-bold uppercase tracking-[0.08em] text-white transition-colors hover:bg-telemetry-ember-dark sm:inline-flex"
                >
                    ⏺ Rekam
                </a>
            @endif

            <x-notification-bell />

            {{-- Menu profil --}}
            <div class="relative">
                <button
                    type="button"
                    @click="userMenu = !userMenu"
                    :aria-expanded="userMenu"
                    class="flex items-center gap-2 rounded border border-transparent p-1 transition-colors hover:border-telemetry-line hover:bg-telemetry-well"
                >
                    @if (Auth::user()->avatar_path)
                        <img src="{{ asset(Auth::user()->avatar_path) }}" alt="Foto profil {{ Auth::user()->name }}" class="h-8 w-8 shrink-0 rounded-full object-cover" />
                    @else
                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-telemetry-ink font-display text-xs font-bold text-white">
                            {{ Auth::user()->initials() }}
                        </span>
                    @endif
                    <span class="hidden max-w-[10rem] truncate font-display text-[11px] font-bold uppercase tracking-[0.06em] text-telemetry-ink md:block">
                        {{ Auth::user()->name }}
                    </span>
                    <svg class="hidden h-3.5 w-3.5 text-telemetry-slate md:block" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>

                <div
                    x-show="userMenu"
                    x-cloak
                    @click.outside="userMenu = false"
                    class="absolute right-0 z-50 mt-2 w-60 border border-telemetry-line bg-white p-1 shadow-overlay"
                >
                    <div class="border-b border-telemetry-line px-3 py-2">
                        <p class="truncate text-sm font-semibold text-telemetry-ink">{{ Auth::user()->name }}</p>
                        <p class="truncate text-xs text-telemetry-slate">
                            {{ Auth::user()->username ? '@'.Auth::user()->username : Auth::user()->email }}
                        </p>
                    </div>

                    <a href="{{ route('profile.edit') }}" class="block px-3 py-2 text-sm font-medium text-telemetry-slate transition-colors hover:bg-telemetry-well hover:text-telemetry-ink">
                        {{ __('Profil') }}
                    </a>

                    @if (Route::has('goals.index'))
                        <a href="{{ route('goals.index') }}" class="block px-3 py-2 text-sm font-medium text-telemetry-slate transition-colors hover:bg-telemetry-well hover:text-telemetry-ink">
                            {{ __('Goals') }}
                        </a>
                    @endif

                    @if (Route::has('settings'))
                        <a href="{{ route('settings') }}" class="block px-3 py-2 text-sm font-medium text-telemetry-slate transition-colors hover:bg-telemetry-well hover:text-telemetry-ink">
                            {{ __('Settings') }}
                        </a>
                    @endif

                    <form method="POST" action="{{ route('logout') }}" class="border-t border-telemetry-line">
                        @csrf
                        <button type="submit" class="block w-full px-3 py-2 text-left text-sm font-semibold text-telemetry-ember-deep transition-colors hover:bg-telemetry-well">
                            {{ __('Log Out') }}
                        </button>
                    </form>
                </div>
            </div>

            <button
                type="button"
                @click="mobileOpen = !mobileOpen"
                :aria-expanded="mobileOpen"
                aria-label="Menu"
                class="flex h-9 w-9 items-center justify-center rounded border border-telemetry-line text-telemetry-slate transition-colors hover:bg-telemetry-well hover:text-telemetry-ink lg:hidden"
            >
                <svg class="h-5 w-5" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                </svg>
            </button>
        </div>
    </div>

    {{-- Strip sekunder (desktop): modul analitik & penjelajahan --}}
    @if ($secondaryNav !== [])
        <div class="hidden border-t border-telemetry-line lg:block">
            <div class="flex items-center justify-between gap-4 px-4 sm:px-6 lg:px-8">
                <nav class="flex items-center gap-4 py-2">
                    @foreach ($secondaryNav as $item)
                        <x-topnav-link variant="sub" :href="route($item['route'])" :active="request()->routeIs($item['active'])">
                            {{ __($item['label']) }}
                        </x-topnav-link>
                    @endforeach
                </nav>
                <p class="telemetry-label hidden xl:block">RAGA // Telemetry Suite</p>
            </div>
        </div>
    @endif

    {{-- Panel mobile: seluruh modul --}}
    <div x-show="mobileOpen" x-cloak class="border-t border-telemetry-line bg-white lg:hidden">
        <nav class="grid grid-cols-2 gap-1 px-4 py-3">
            @foreach ($mobileNav as $item)
                <a
                    href="{{ route($item['route']) }}"
                    @class([
                        'flex items-center gap-2 rounded px-3 py-2 text-sm font-semibold transition-colors',
                        'bg-telemetry-ink text-white' => request()->routeIs($item['active']),
                        'text-telemetry-slate hover:bg-telemetry-well hover:text-telemetry-ink' => ! request()->routeIs($item['active']),
                    ])
                >
                    <span class="text-base leading-none" aria-hidden="true">{{ $item['icon'] }}</span>
                    <span class="truncate">{{ __($item['label']) }}</span>
                </a>
            @endforeach
        </nav>
    </div>
</header>
