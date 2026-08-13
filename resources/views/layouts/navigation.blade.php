<nav x-data="{ open: false }" class="sticky top-0 z-40 bg-white/80 dark:bg-gray-900/80 backdrop-blur-lg border-b border-gray-100 dark:border-gray-800">
    <!-- Primary Navigation Menu -->
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16 items-center">
            <div class="flex items-center gap-8">
                <!-- Logo -->
                <a href="{{ route('dashboard') }}" class="shrink-0">
                    <x-application-logo />
                </a>

                <!-- Navigation Links -->
                <div class="hidden sm:flex sm:items-center sm:gap-1">
                    <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                        {{ __('Dashboard') }}
                    </x-nav-link>
                    <x-nav-link :href="route('training')" :active="request()->routeIs('training*')">
                        {{ __('Training') }}
                    </x-nav-link>
                    <x-nav-link :href="route('health')" :active="request()->routeIs('health*')">
                        {{ __('Health') }}
                    </x-nav-link>
                    <x-nav-link :href="route('recovery')" :active="request()->routeIs('recovery')">
                        {{ __('Recovery') }}
                    </x-nav-link>
                    <x-nav-link :href="route('ai')" :active="request()->routeIs('ai')">
                        {{ __('AI') }}
                    </x-nav-link>
                    <x-nav-link :href="route('settings')" :active="request()->routeIs('settings')">
                        {{ __('Settings') }}
                    </x-nav-link>
                </div>
            </div>

            <!-- Settings Dropdown -->
            <div class="hidden sm:flex sm:items-center">
                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button class="flex items-center gap-2 rounded-full pl-1 pr-3 py-1 hover:bg-gray-100 dark:hover:bg-gray-800 transition focus:outline-none">
                            <span class="flex h-8 w-8 items-center justify-center rounded-full bg-gradient-to-br from-raga-accent to-raga-primary text-white text-sm font-bold">
                                {{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
                            </span>
                            <span class="text-sm font-semibold text-gray-700 dark:text-gray-200">{{ Auth::user()->name }}</span>
                            <svg class="h-4 w-4 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                            </svg>
                        </button>
                    </x-slot>

                    <x-slot name="content">
                        <x-dropdown-link :href="route('profile.edit')">
                            {{ __('Profile') }}
                        </x-dropdown-link>

                        <!-- Authentication -->
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf

                            <x-dropdown-link :href="route('logout')"
                                    onclick="event.preventDefault();
                                                this.closest('form').submit();">
                                {{ __('Log Out') }}
                            </x-dropdown-link>
                        </form>
                    </x-slot>
                </x-dropdown>
            </div>

            <!-- Hamburger -->
            <div class="-me-2 flex items-center sm:hidden">
                <button @click="open = ! open" class="inline-flex items-center justify-center p-2 rounded-full text-gray-400 hover:text-gray-600 hover:bg-gray-100 dark:hover:bg-gray-800 focus:outline-none transition">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Responsive Navigation Menu -->
    <div :class="{'block': open, 'hidden': ! open}" class="hidden sm:hidden border-t border-gray-100 dark:border-gray-800">
        <div class="px-4 pt-3 pb-3 space-y-1">
            <x-responsive-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                {{ __('Dashboard') }}
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('training')" :active="request()->routeIs('training*')">
                {{ __('Training') }}
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('health')" :active="request()->routeIs('health*')">
                {{ __('Health') }}
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('recovery')" :active="request()->routeIs('recovery')">
                {{ __('Recovery') }}
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('ai')" :active="request()->routeIs('ai')">
                {{ __('AI') }}
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('settings')" :active="request()->routeIs('settings')">
                {{ __('Settings') }}
            </x-responsive-nav-link>
        </div>

        <!-- Responsive Settings Options -->
        <div class="pt-3 pb-4 border-t border-gray-100 dark:border-gray-800">
            <div class="flex items-center gap-3 px-4">
                <span class="flex h-10 w-10 items-center justify-center rounded-full bg-gradient-to-br from-raga-accent to-raga-primary text-white text-sm font-bold">
                    {{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
                </span>
                <div>
                    <div class="font-bold text-base text-gray-800 dark:text-gray-100">{{ Auth::user()->name }}</div>
                    <div class="font-medium text-sm text-gray-500 dark:text-gray-400">{{ Auth::user()->email }}</div>
                </div>
            </div>

            <div class="mt-3 px-4 space-y-1">
                <x-responsive-nav-link :href="route('profile.edit')">
                    {{ __('Profile') }}
                </x-responsive-nav-link>

                <!-- Authentication -->
                <form method="POST" action="{{ route('logout') }}">
                    @csrf

                    <x-responsive-nav-link :href="route('logout')"
                            onclick="event.preventDefault();
                                        this.closest('form').submit();">
                        {{ __('Log Out') }}
                    </x-responsive-nav-link>
                </form>
            </div>
        </div>
    </div>
</nav>
