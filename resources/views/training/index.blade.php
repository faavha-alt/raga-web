<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-100 leading-tight">
            {{ __('Training') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8">
            @if ($plans->isEmpty())
                <x-card class="text-center py-12">
                    <p class="text-lg font-semibold text-gray-900 dark:text-gray-100">No Training Plan Yet</p>
                    <p class="mt-2 text-gray-500 dark:text-gray-400">Create a training plan to see your weekly schedule here.</p>
                </x-card>
            @else
                <div class="space-y-4">
                    @foreach ($plans as $plan)
                        <x-card>
                            <p class="font-semibold text-gray-900 dark:text-gray-100">{{ $plan->name }}</p>
                            <p class="text-sm text-gray-500 dark:text-gray-400">{{ ucfirst($plan->status) }}</p>
                        </x-card>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
