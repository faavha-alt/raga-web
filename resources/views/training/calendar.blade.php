<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="telemetry-value text-2xl sm:text-3xl lg:text-5xl">{{ __('Training Calendar') }}</h1>
            <p class="mt-1 text-sm font-medium text-telemetry-slate">Kalender latihan & rest days kamu.</p>
        </div>
    </x-slot>

    <div class="py-4 sm:py-6 pb-16">
        <div class="px-4 sm:px-6 lg:px-8 space-y-3 sm:space-y-6">

            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                <x-metric-tile icon="🏃" label="Hari Aktif" :value="$calendar['summary']['active_days']" />
                <x-metric-tile icon="😴" label="Rest Days" :value="$calendar['summary']['rest_days']" />
                <x-metric-tile icon="🔥" label="Konsistensi" :value="$consistency['consistency_percent']" unit="%" />
                <x-metric-tile icon="⚡" label="Streak Aktif" :value="$consistency['current_streak_days']" />
            </div>

            <x-card>
                <div class="mb-4 flex items-center justify-between">
                    <a href="{{ route('training.calendar', ['month' => $calendar['prev_month']]) }}" class="telemetry-label transition-colors hover:text-telemetry-ink">← Prev</a>
                    <p class="telemetry-value text-sm">{{ $calendar['month_label'] }}</p>
                    <a href="{{ route('training.calendar', ['month' => $calendar['next_month']]) }}" class="telemetry-label transition-colors hover:text-telemetry-ink">Next →</a>
                </div>

                <div class="mb-1 grid grid-cols-7 gap-1 text-center">
                    @foreach (['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'] as $d)
                        <div class="telemetry-label">{{ $d }}</div>
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
                            class="relative aspect-square rounded border border-transparent flex flex-col items-center justify-center gap-0.5 text-xs transition hover:border-telemetry-line-strong hover:bg-telemetry-well
                                {{ $day['is_today'] ? 'border-telemetry-ember ring-1 ring-telemetry-ember' : '' }}
                                {{ $day['in_month'] && $hasActual ? 'bg-[rgba(0,184,101,0.08)]' : ($day['in_month'] && $day['is_rest_day'] ? 'bg-telemetry-well' : '') }}"
                        >
                            <span class="telemetry-value {{ $day['in_month'] ? 'text-telemetry-ink' : 'text-telemetry-slate/40' }}">{{ $day['day'] }}</span>
                            @if ($hasActual)
                                <span class="text-[10px] leading-none">{{ $day['workouts'][0]['icon'] }}</span>
                            @elseif ($hasPlanned)
                                <span class="text-[10px] leading-none opacity-50">{{ $day['planned_workouts'][0]['icon'] }}</span>
                            @endif
                            @if ($hasPlanned)
                                <span class="absolute top-1 right-1 h-1.5 w-1.5 rounded-full bg-telemetry-emerald" title="Ada rencana latihan"></span>
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
                    <p class="mb-3 telemetry-label-lg text-telemetry-ink">Rencana Latihan</p>
                    <div class="space-y-3">
                        @foreach ($plannedDays as $day)
                            <x-card id="plan-{{ $day['date'] }}" class="!p-4 scroll-mt-6">
                                <p class="telemetry-label">{{ \Illuminate\Support\Carbon::parse($day['date'])->translatedFormat('l, d M') }}</p>
                                <div class="mt-2 space-y-3">
                                    @foreach ($day['planned_workouts'] as $pw)
                                        <div class="flex items-start gap-3">
                                            <span class="text-xl leading-none">{{ $pw['icon'] }}</span>
                                            <div class="min-w-0">
                                                <p class="font-bold text-telemetry-ink">
                                                    {{ \App\Support\ActivityTypeIcon::label($pw['type']) }}
                                                    @if ($pw['intensity'])
                                                        <span class="ml-1 inline-flex h-5 items-center rounded border border-telemetry-line bg-telemetry-well px-2 text-[10px] font-bold uppercase tracking-[0.08em] text-telemetry-slate">{{ $pw['intensity'] }}</span>
                                                    @endif
                                                </p>
                                                <p class="mt-0.5 text-[11px] text-telemetry-slate">
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
                                                    <p class="mt-2 text-sm text-telemetry-slate"><span class="font-semibold text-telemetry-ink">Warm-up:</span> {{ $pw['warm_up'] }}</p>
                                                @endif
                                                @if ($pw['main_set'])
                                                    <p class="mt-1 text-sm text-telemetry-slate"><span class="font-semibold text-telemetry-ink">Main set:</span> {{ $pw['main_set'] }}</p>
                                                @endif
                                                @if ($pw['cool_down'])
                                                    <p class="mt-1 text-sm text-telemetry-slate"><span class="font-semibold text-telemetry-ink">Cool-down:</span> {{ $pw['cool_down'] }}</p>
                                                @endif
                                                @if ($pw['notes'])
                                                    <p class="mt-1 text-sm italic text-telemetry-slate">{{ $pw['notes'] }}</p>
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
