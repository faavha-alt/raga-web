<x-app-layout>
    <x-slot name="header">
        <h2 class="text-2xl font-extrabold text-gray-900 dark:text-white leading-tight">
            {{ __('Settings') }}
        </h2>
    </x-slot>

    <div class="py-6 pb-16">
        <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8">
            <x-card class="!p-0 divide-y divide-gray-100 dark:divide-gray-700 overflow-hidden">
                <a href="{{ route('profile.edit') }}" class="block px-6 py-4 font-medium text-gray-900 dark:text-gray-100 hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                    Account
                </a>
                <a href="{{ route('settings.garmin.show') }}" class="flex items-center justify-between px-6 py-4 font-medium text-gray-900 dark:text-gray-100 hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                    <span>⌚ Garmin Connect</span>
                    @if (auth()->user()->garminConnection?->connected_at)
                        <span class="text-xs font-bold uppercase tracking-wide text-raga-excellent">Connected</span>
                    @else
                        <span class="text-xs font-bold uppercase tracking-wide text-gray-400">Not connected</span>
                    @endif
                </a>
                @foreach ($rows as $row)
                    <div class="px-6 py-4 text-gray-400 dark:text-gray-500">{{ $row }}</div>
                @endforeach
            </x-card>
        </div>
    </div>
</x-app-layout>
