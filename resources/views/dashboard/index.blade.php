<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-100 leading-tight">
            {{ __('Good Morning, :name', ['name' => auth()->user()->name]) }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="grid grid-cols-2 gap-4">
                <x-score-tile title="Health Score" :score="$healthScore?->overall_score" :category="$healthScore?->category()" />
                <x-score-tile title="Recovery" :score="$recoveryScore?->score" :category="$recoveryScore?->category()" />
            </div>

            <x-card>
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Sleep</p>
                @if ($sleep)
                    @php $minutes = $sleep->totalDurationMinutes(); @endphp
                    <p class="mt-1 text-2xl font-bold text-gray-900 dark:text-gray-100">
                        {{ intdiv((int) $minutes, 60) }}h {{ (int) $minutes % 60 }}m
                    </p>
                @else
                    <p class="mt-1 text-gray-500 dark:text-gray-400">No sleep data yet</p>
                @endif
            </x-card>

            <x-card>
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Today's Training</p>
                <p class="mt-1 text-gray-500 dark:text-gray-400">No training plan yet</p>
            </x-card>

            <x-card>
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Activity</p>
                @if ($activity)
                    <p class="mt-1 text-2xl font-bold text-gray-900 dark:text-gray-100">{{ $activity->steps }} steps</p>
                @else
                    <p class="mt-1 text-gray-500 dark:text-gray-400">No activity data yet</p>
                @endif
            </x-card>

            <x-card>
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">AI Insight</p>
                <p class="mt-1 text-gray-900 dark:text-gray-100">Connect a health data source to get personalized insights.</p>
            </x-card>
        </div>
    </div>
</x-app-layout>
