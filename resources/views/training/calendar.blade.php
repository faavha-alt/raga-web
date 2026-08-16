<x-app-layout>
    <x-slot name="header">
        <h2 class="text-2xl font-extrabold text-gray-900 leading-tight">
            {{ __('Training Calendar') }}
        </h2>
        <p class="mt-1 text-sm font-medium text-gray-500">Kalender latihan & rest days kamu.</p>
    </x-slot>

    <div class="py-6 pb-16">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                <x-metric-tile icon="🏃" label="Hari Aktif" :value="$calendar['summary']['active_days']" />
                <x-metric-tile icon="😴" label="Rest Days" :value="$calendar['summary']['rest_days']" />
                <x-metric-tile icon="🔥" label="Konsistensi" :value="$consistency['consistency_percent']" unit="%" />
                <x-metric-tile icon="⚡" label="Streak Aktif" :value="$consistency['current_streak_days']" />
            </div>

            <x-card>
                <div class="flex items-center justify-between mb-4">
                    <a href="{{ route('training.calendar', ['month' => $calendar['prev_month']]) }}" class="text-sm font-bold text-gray-400 hover:text-gray-700 transition">← Prev</a>
                    <p class="text-sm font-bold text-gray-900">{{ $calendar['month_label'] }}</p>
                    <a href="{{ route('training.calendar', ['month' => $calendar['next_month']]) }}" class="text-sm font-bold text-gray-400 hover:text-gray-700 transition">Next →</a>
                </div>

                <div class="grid grid-cols-7 gap-1 text-center text-[10px] font-bold uppercase text-gray-400 mb-1">
                    @foreach (['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'] as $d)
                        <div>{{ $d }}</div>
                    @endforeach
                </div>

                <div class="grid grid-cols-7 gap-1">
                    @foreach ($calendar['days'] as $day)
                        <a
                            href="{{ route('activities', ['from' => $day['date'], 'to' => $day['date']]) }}"
                            class="relative aspect-square rounded-xl flex flex-col items-center justify-center gap-0.5 text-xs transition hover:ring-2 hover:ring-raga-primary/40
                                {{ $day['is_today'] ? 'ring-2 ring-raga-primary' : '' }}
                                {{ $day['in_month'] && count($day['workouts']) > 0 ? 'bg-emerald-50' : ($day['in_month'] && $day['is_rest_day'] ? 'bg-gray-50' : '') }}"
                        >
                            <span class="font-bold {{ $day['in_month'] ? 'text-gray-700' : 'text-gray-300' }}">{{ $day['day'] }}</span>
                            @if (count($day['workouts']) > 0)
                                <span class="text-[10px] leading-none">{{ $day['workouts'][0]['icon'] }}</span>
                            @elseif (count($day['planned_workouts']) > 0)
                                <span class="text-[10px] leading-none opacity-50">{{ $day['planned_workouts'][0]['icon'] }}</span>
                            @endif
                            @if (count($day['planned_workouts']) > 0)
                                <span class="absolute top-1 right-1 h-1.5 w-1.5 rounded-full bg-raga-accent" title="Ada rencana latihan"></span>
                            @endif
                        </a>
                    @endforeach
                </div>
            </x-card>

        </div>
    </div>
</x-app-layout>
