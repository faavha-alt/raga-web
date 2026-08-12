<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-100 leading-tight">
            {{ __('Settings') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8">
            <x-card class="!p-0 divide-y divide-gray-100 dark:divide-gray-700">
                <a href="{{ route('profile.edit') }}" class="block px-6 py-4 text-gray-900 dark:text-gray-100 hover:bg-gray-50 dark:hover:bg-gray-700">
                    Account
                </a>
                @foreach ($rows as $row)
                    <div class="px-6 py-4 text-gray-900 dark:text-gray-100">{{ $row }}</div>
                @endforeach
            </x-card>
        </div>
    </div>
</x-app-layout>
