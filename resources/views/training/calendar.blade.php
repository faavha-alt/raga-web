<x-app-layout>
    <x-slot name="header">
        <h2 class="text-2xl font-extrabold text-gray-900 leading-tight">
            {{ __('Training Calendar') }}
        </h2>
        <p class="mt-1 text-sm font-medium text-gray-500">Kalender latihan & rest days kamu.</p>
    </x-slot>

    <div class="py-6 pb-16">
        <div class="px-4 sm:px-6 lg:px-8 space-y-6">

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
                        @php
                            $hasActual = count($day['workouts']) > 0;
                            $hasPlanned = count($day['planned_workouts']) > 0;
                            $href = $hasActual
                                ? route('activities', ['from' => $day['date'], 'to' => $day['date']])
                                : ($hasPlanned ? '#plan-'.$day['date'] : null);
                        @endphp
                        <a
                            href="{{ $href ?? '#' }}"
                            class="relative aspect-square rounded-xl flex flex-col items-center justify-center gap-0.5 text-xs transition hover:ring-2 hover:ring-raga-primary/40
                                {{ $day['is_today'] ? 'ring-2 ring-raga-primary' : '' }}
                                {{ $day['in_month'] && $hasActual ? 'bg-emerald-50' : ($day['in_month'] && $day['is_rest_day'] ? 'bg-gray-50' : '') }}"
                        >
                            <span class="font-bold {{ $day['in_month'] ? 'text-gray-700' : 'text-gray-300' }}">{{ $day['day'] }}</span>
                            @if ($hasActual)
                                <span class="text-[10px] leading-none">{{ $day['workouts'][0]['icon'] }}</span>
                            @elseif ($hasPlanned)
                                <span class="text-[10px] leading-none opacity-50">{{ $day['planned_workouts'][0]['icon'] }}</span>
                            @endif
                            @if ($hasPlanned)
                                <span class="absolute top-1 right-1 h-1.5 w-1.5 rounded-full bg-raga-accent" title="Ada rencana latihan"></span>
                            @endif
                        </a>
                    @endforeach
                </div>
            </x-card>

            @php
                $plannedDays = collect($calendar['days'])->filter(fn ($d) => $d['in_month'] && count($d['planned_workouts']) > 0);
            @endphp

            @if ($plannedDays->isNotEmpty())
                <div>
                    <h3 class="mb-3 text-xs font-bold uppercase tracking-wider text-gray-400">📋 Rencana Latihan</h3>
                    <div class="space-y-3">
                        @foreach ($plannedDays as $day)
                            <x-card id="plan-{{ $day['date'] }}" class="!p-4 scroll-mt-6">
                                <p class="text-xs font-bold text-gray-400">{{ \Illuminate\Support\Carbon::parse($day['date'])->translatedFormat('l, d M') }}</p>
                                <div class="mt-2 space-y-3">
                                    @foreach ($day['planned_workouts'] as $pw)
                                        <div class="flex items-start gap-3">
                                            <span class="text-xl leading-none">{{ $pw['icon'] }}</span>
                                            <div class="min-w-0">
                                                <p class="font-bold text-gray-900">
                                                    {{ \App\Support\ActivityTypeIcon::label($pw['type']) }}
                                                    @if ($pw['intensity'])
                                                        <span class="ml-1 rounded-full bg-gray-100 px-2 py-0.5 text-[10px] font-bold uppercase text-gray-500">{{ $pw['intensity'] }}</span>
                                                    @endif
                                                </p>
                                                <p class="mt-0.5 text-xs text-gray-500">
                                                    @if ($pw['duration_minutes'])
                                                        {{ round($pw['duration_minutes']) }} menit
                                                    @endif
                                                    @if ($pw['distance_meters'])
                                                        · {{ number_format($pw['distance_meters'] / 1000, 1) }} km
                                                    @endif
                                                    @if ($pw['target_heart_rate_zone'])
                                                        · Zona HR {{ $pw['target_heart_rate_zone'] }}
                                                    @endif
                                                </p>
                                                @if ($pw['warm_up'])
                                                    <p class="mt-2 text-sm text-gray-600"><span class="font-semibold text-gray-700">Warm-up:</span> {{ $pw['warm_up'] }}</p>
                                                @endif
                                                @if ($pw['main_set'])
                                                    <p class="mt-1 text-sm text-gray-600"><span class="font-semibold text-gray-700">Main set:</span> {{ $pw['main_set'] }}</p>
                                                @endif
                                                @if ($pw['cool_down'])
                                                    <p class="mt-1 text-sm text-gray-600"><span class="font-semibold text-gray-700">Cool-down:</span> {{ $pw['cool_down'] }}</p>
                                                @endif
                                                @if ($pw['notes'])
                                                    <p class="mt-1 text-sm text-gray-500 italic">{{ $pw['notes'] }}</p>
                                                @endif
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </x-card>
                        @endforeach
                    </div>
                </div>
            @endif

        </div>
    </div>
</x-app-layout>
