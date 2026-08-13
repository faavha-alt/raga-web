<x-app-layout>
    <x-slot name="header">
        <h2 class="text-2xl font-extrabold text-gray-900 dark:text-white leading-tight">
            {{ __('Good Morning, :name 👋', ['name' => explode(' ', auth()->user()->name)[0]]) }}
        </h2>
        <p class="mt-1 text-sm font-medium text-gray-500 dark:text-gray-400">Ini progress kamu hari ini.</p>
    </x-slot>

    <div class="py-6 pb-16">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">
            <div class="grid grid-cols-2 gap-4">
                <x-score-tile title="Health Score" :score="$healthScore?->overall_score" :category="$healthScore?->category()" />
                <x-score-tile title="Recovery" :score="$recoveryScore?->score" :category="$recoveryScore?->category()" />
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <x-card>
                    <p class="text-xs font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500">😴 Sleep</p>
                    @if ($sleep)
                        @php $minutes = $sleep->totalDurationMinutes(); @endphp
                        <p class="mt-2 text-3xl font-black text-gray-900 dark:text-gray-100">
                            {{ intdiv((int) $minutes, 60) }}h {{ (int) $minutes % 60 }}m
                        </p>
                    @else
                        <p class="mt-2 text-gray-400 dark:text-gray-500">Belum ada data tidur</p>
                    @endif
                </x-card>

                <x-card>
                    <p class="text-xs font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500">🏃 Activity</p>
                    @if ($activity)
                        <p class="mt-2 text-3xl font-black text-gray-900 dark:text-gray-100">{{ $activity->steps }} <span class="text-base font-semibold text-gray-400">steps</span></p>
                    @else
                        <p class="mt-2 text-gray-400 dark:text-gray-500">Belum ada data aktivitas</p>
                    @endif
                </x-card>
            </div>

            <x-card>
                <p class="text-xs font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500">📅 Today's Training</p>
                <p class="mt-2 text-gray-400 dark:text-gray-500">Belum ada training plan</p>
            </x-card>

            <x-card class="bg-gradient-to-br from-raga-accent/5 to-raga-primary/5 border-raga-primary/10">
                <p class="text-xs font-bold uppercase tracking-wider text-raga-primary">✨ AI Insight</p>
                <p class="mt-2 text-gray-700 dark:text-gray-200">Hubungkan sumber data kesehatan untuk dapat insight yang personal buat kamu.</p>
            </x-card>
        </div>
    </div>
</x-app-layout>
