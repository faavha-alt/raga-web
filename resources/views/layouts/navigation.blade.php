@php
    $navItems = [
        ['route' => 'dashboard', 'active' => 'dashboard', 'icon' => '🏠', 'label' => 'Dashboard'],
        ['route' => 'training', 'active' => 'training*', 'icon' => '🏋️', 'label' => 'Training'],
        ['route' => 'running', 'active' => 'running*', 'icon' => '🏃', 'label' => 'Running'],
        ['route' => 'trail', 'active' => 'trail*', 'icon' => '⛰️', 'label' => 'Trail'],
        ['route' => 'health', 'active' => 'health*', 'icon' => '❤️', 'label' => 'Health'],
        ['route' => 'recovery', 'active' => 'recovery', 'icon' => '🔋', 'label' => 'Recovery'],
        ['route' => 'analytics', 'active' => 'analytics*', 'icon' => '📊', 'label' => 'Analytics'],
        ['route' => 'ai', 'active' => 'ai', 'icon' => '🤖', 'label' => 'AI'],
        ['route' => 'settings', 'active' => 'settings', 'icon' => '⚙️', 'label' => 'Settings'],
    ];
@endphp

<!-- Mobile top bar -->
<div class="sticky top-0 z-30 flex h-14 items-center justify-between border-b border-gray-100 bg-white/80 px-4 backdrop-blur-lg dark:border-gray-800 dark:bg-gray-900/80 lg:hidden">
    <a href="{{ route('dashboard') }}" class="shrink-0">
        <x-application-logo />
    </a>
    <button @click="sidebarOpen = true" class="rounded-full p-2 text-gray-400 transition hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-800">
        <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
        </svg>
    </button>
</div>

<!-- Mobile backdrop -->
<div
    x-show="sidebarOpen"
    x-cloak
    @click="sidebarOpen = false"
    class="fixed inset-0 z-40 bg-black/40 lg:hidden"
    x-transition:enter="transition-opacity ease-out duration-200"
    x-transition:enter-start="opacity-0"
    x-transition:enter-end="opacity-100"
    x-transition:leave="transition-opacity ease-in duration-150"
    x-transition:leave-start="opacity-100"
    x-transition:leave-end="opacity-0"
></div>

<!-- Sidebar -->
<aside
    :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
    class="fixed inset-y-0 left-0 z-50 flex w-72 shrink-0 flex-col border-r border-gray-100 bg-white transition-transform duration-200 ease-in-out dark:border-gray-800 dark:bg-gray-900 lg:sticky lg:top-0 lg:h-screen lg:translate-x-0"
>
    <div class="flex h-16 shrink-0 items-center justify-between px-5">
        <a href="{{ route('dashboard') }}">
            <x-application-logo />
        </a>
        <button @click="sidebarOpen = false" class="rounded-full p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-800 lg:hidden">
            <svg class="h-5 w-5" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>
    </div>

    <nav class="flex-1 space-y-1 overflow-y-auto px-3 py-2">
        @foreach ($navItems as $item)
            <x-sidebar-link :href="route($item['route'])" :active="request()->routeIs($item['active'])" :icon="$item['icon']">
                {{ __($item['label']) }}
            </x-sidebar-link>
        @endforeach
    </nav>

    <div class="flex shrink-0 items-center gap-2 border-t border-gray-100 p-3 dark:border-gray-800">
        <a href="{{ route('profile.edit') }}" class="flex min-w-0 flex-1 items-center gap-3 rounded-2xl px-2 py-2 transition hover:bg-gray-100 dark:hover:bg-gray-800">
            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-raga-accent to-raga-primary text-sm font-bold text-white">
                {{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
            </span>
            <span class="min-w-0 flex-1">
                <span class="block truncate text-sm font-semibold text-gray-700 dark:text-gray-200">{{ Auth::user()->name }}</span>
                <span class="block truncate text-xs text-gray-400">{{ Auth::user()->email }}</span>
            </span>
        </a>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" title="{{ __('Log Out') }}" class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-gray-400 transition hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-800 dark:hover:text-gray-200">
                <svg class="h-5 w-5" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                </svg>
            </button>
        </form>
    </div>
</aside>
