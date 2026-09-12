<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="telemetry-value text-4xl sm:text-5xl">{{ __('Running') }}</h1>
            <p class="mt-2 text-sm font-medium text-telemetry-slate">Ringkasan performa lari kamu dari data Garmin.</p>
        </div>
    </x-slot>

    <div class="py-6 pb-16">
        <div class="px-4 sm:px-6 lg:px-8 space-y-6">

            <div class="grid sm:grid-cols-2 gap-4">
                <x-card>
                    <div class="mb-4 flex items-end justify-between gap-4">
                        <p class="telemetry-label">Volume 7 Hari</p>
                        <a href="{{ route('running.distance') }}" class="font-display text-[11px] font-bold uppercase tracking-[0.08em] text-telemetry-chrono hover:underline">Detail →</a>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <p class="telemetry-label">Jarak</p>
                            <p class="mt-1 telemetry-value text-xl">{{ number_format($weekTotals['distance_meters'] / 1000, 1) }}<span class="ml-1 text-[10px] font-semibold uppercase tracking-[0.08em] text-telemetry-slate">km</span></p>
                        </div>
                        <div>
                            <p class="telemetry-label">Lari</p>
                            <p class="mt-1 telemetry-value text-xl">{{ $weekTotals['activity_count'] }}</p>
                        </div>
                        <div>
                            @php $p = $weekTotals['average_pace_seconds_per_km'] ? (int) round($weekTotals['average_pace_seconds_per_km']) : null; @endphp
                            <p class="telemetry-label">Pace Rata-rata</p>
                            <p class="mt-1 telemetry-value text-xl">
                                {{ $p ? sprintf('%d:%02d', intdiv($p, 60), $p % 60) : '--' }}<span class="ml-1 text-[10px] font-semibold uppercase tracking-[0.08em] text-telemetry-slate">/km</span>
                            </p>
                        </div>
                        <div>
                            <p class="telemetry-label">Avg HR</p>
                            <p class="mt-1 telemetry-value text-xl">{{ $weekTotals['average_heart_rate'] ? round($weekTotals['average_heart_rate']) : '--' }}<span class="ml-1 text-[10px] font-semibold uppercase tracking-[0.08em] text-telemetry-slate">bpm</span></p>
                        </div>
                    </div>
                </x-card>

                @php
                    $categoryClasses = fn ($category) => match ($category->value) {
                        'excellent', 'very_good' => 'border-telemetry-emerald/20 bg-[rgba(0,184,101,0.08)] text-telemetry-emerald-deep',
                        'good' => 'border-telemetry-chrono/20 bg-[rgba(0,112,243,0.08)] text-telemetry-chrono-deep',
                        'moderate' => 'border-[rgba(245,158,11,0.25)] bg-[rgba(245,158,11,0.10)] text-telemetry-amber',
                        default => 'border-telemetry-ember/25 bg-[rgba(255,62,29,0.08)] text-telemetry-ember-deep',
                    };
                @endphp
                <x-card>
                    <div class="mb-4 flex items-end justify-between gap-4">
                        <p class="telemetry-label">Running Performance</p>
                        <a href="{{ route('running.pace') }}" class="font-display text-[11px] font-bold uppercase tracking-[0.08em] text-telemetry-chrono hover:underline">Detail →</a>
                    </div>
                    @if ($performance)
                        @php $category = \App\Support\ScoreCategory::fromScore($performance['score']); @endphp
                        <div class="flex items-center justify-between">
                            <p class="telemetry-value text-3xl">{{ $performance['score'] }}</p>
                            <span class="inline-flex h-5 items-center rounded border px-2 text-[10px] font-bold uppercase tracking-[0.08em] {{ $categoryClasses($category) }}">{{ $category->label() }}</span>
                        </div>
                        <div class="mt-3 space-y-1.5 border-t border-telemetry-line pt-3">
                            @foreach ($performance['factors'] as $factor)
                                <div class="flex items-center justify-between text-xs">
                                    <span class="text-telemetry-slate">{{ $factor['label'] }}</span>
                                    @if ($factor['insufficient_data'])
                                        <span class="text-telemetry-slate/50">Belum cukup data</span>
                                    @else
                                        <span class="telemetry-value {{ $factor['contribution'] > 0 ? 'text-telemetry-emerald-deep' : ($factor['contribution'] < 0 ? 'text-telemetry-ember-deep' : 'text-telemetry-slate') }}">
                                            {{ $factor['contribution'] > 0 ? '+' : '' }}{{ $factor['contribution'] }}
                                        </span>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-sm text-telemetry-slate">Belum cukup data (minimal 7 lari dalam 30 hari terakhir).</p>
                    @endif
                </x-card>

                <x-card>
                    <p class="mb-4 telemetry-label">Konsistensi 30 Hari</p>
                    <div class="grid grid-cols-3 gap-4">
                        <div>
                            <p class="telemetry-label">Konsisten</p>
                            <p class="mt-1 telemetry-value text-xl">{{ $consistency['consistency_percent'] }}%</p>
                        </div>
                        <div>
                            <p class="telemetry-label">Hari Lari</p>
                            <p class="mt-1 telemetry-value text-xl">{{ $consistency['days_with_workout'] }}</p>
                        </div>
                        <div>
                            <p class="telemetry-label">Streak Aktif</p>
                            <p class="mt-1 telemetry-value text-xl">{{ $consistency['current_streak_days'] }}</p>
                        </div>
                    </div>
                </x-card>

                <x-card>
                    <p class="mb-4 telemetry-label">VO2 Max</p>
                    @if ($latestVo2max)
                        <p class="telemetry-value text-2xl">{{ number_format($latestVo2max['value'], 1) }}<span class="ml-1.5 text-[10px] font-semibold uppercase tracking-[0.08em] text-telemetry-slate">ml/kg/min</span></p>
                        <p class="mt-1 text-[11px] text-telemetry-slate">{{ \Illuminate\Support\Carbon::parse($latestVo2max['date'])->translatedFormat('d M Y') }}</p>
                    @else
                        <p class="text-sm text-telemetry-slate">Belum ada data VO2 Max.</p>
                    @endif
                </x-card>
            </div>

            @if ($longestRuns->isNotEmpty())
                <div>
                    <div class="mb-3 flex items-end justify-between gap-4">
                        <p class="telemetry-label-lg text-telemetry-ink">Lari Terjauh</p>
                        <a href="{{ route('running.records') }}" class="font-display text-[11px] font-bold uppercase tracking-[0.08em] text-telemetry-chrono hover:underline">Lihat semua →</a>
                    </div>
                    <div class="grid grid-cols-3 gap-3">
                        @foreach ($longestRuns as $run)
                            <a href="{{ route('activities.show', $run) }}">
                                <x-card class="!p-4">
                                    <p class="telemetry-value text-xl">{{ number_format($run->distance_meters / 1000, 2) }}<span class="ml-1 text-[10px] font-semibold uppercase tracking-[0.08em] text-telemetry-slate">km</span></p>
                                    <p class="mt-1 text-[11px] text-telemetry-slate">{{ $run->start_date->translatedFormat('d M Y') }}</p>
                                </x-card>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif

            @if ($personalRecords->isNotEmpty())
                <div>
                    <div class="mb-3 flex items-end justify-between gap-4">
                        <p class="telemetry-label-lg text-telemetry-ink">Personal Records</p>
                        <a href="{{ route('running.records') }}" class="font-display text-[11px] font-bold uppercase tracking-[0.08em] text-telemetry-chrono hover:underline">Lihat semua →</a>
                    </div>
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                        @foreach ($personalRecords->take(6) as $pr)
                            <x-card class="!p-4">
                                <p class="telemetry-label">{{ $pr->label() }}</p>
                                <p class="mt-1.5 telemetry-value text-xl">{{ $pr->formattedValue() }}</p>
                                <p class="mt-1 text-[11px] text-telemetry-slate">{{ $pr->achieved_date->translatedFormat('d M Y') }}</p>
                            </x-card>
                        @endforeach
                    </div>
                </div>
            @endif

        </div>
    </div>
</x-app-layout>
