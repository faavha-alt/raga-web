<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-100 leading-tight">
            {{ __('AI') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8">
            <div class="flex gap-2 mb-6">
                <span class="px-4 py-2 rounded-full bg-raga-accent text-white text-sm font-medium">Chat</span>
                <span class="px-4 py-2 rounded-full bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 text-sm font-medium">Daily Brief</span>
                <span class="px-4 py-2 rounded-full bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 text-sm font-medium">Weekly Review</span>
            </div>

            <x-card class="text-center py-16">
                <p class="text-lg font-semibold text-gray-900 dark:text-gray-100">AI Assistant Coming Soon</p>
                <p class="mt-2 text-gray-500 dark:text-gray-400">Connect your health data to start getting personalized guidance.</p>
            </x-card>
        </div>
    </div>
</x-app-layout>
