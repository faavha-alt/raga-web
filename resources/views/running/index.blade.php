<x-app-layout>
    <x-slot name="header">
        <h2 class="text-2xl font-extrabold text-gray-900 leading-tight">
            {{ __('Running') }}
        </h2>
        <p class="mt-1 text-sm font-medium text-gray-500">Ringkasan performa lari kamu dari data Garmin.</p>
    </x-slot>

    <div class="py-6 pb-16">
        <div class="px-4 sm:px-6 lg:px-8 space-y-6">

            <div class="grid sm:grid-cols-2 gap-4">
                <x-card>
                    <div class="flex items-center justify-between mb-3">
                        <p class="text-xs font-bold uppercase tracking-wider text-gray-400">🏃 Volume 7 Hari</p>
                        <a href="{{ route('running.distance') }}" class="text-xs font-bold text-raga-primary hover:text-raga-accent transition">Detail →</a>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <p class="text-xl font-black text-gray-900">{{ number_format($weekTotals['distance_meters'] / 1000, 1) }} <span class="text-xs font-semibold text-gray-400">km</span></p>
                            <p class="text-[11px] text-gray-400">Jarak</p>
                        </div>
                        <div>
                            <p class="text-xl font-black text-gray-900">{{ $weekTotals['activity_count'] }}</p>
                            <p class="text-[11px] text-gray-400">Lari</p>
                        </div>
                        <div>
                            @php $p = $weekTotals['average_pace_seconds_per_km'] ? (int) round($weekTotals['average_pace_seconds_per_km']) : null; @endphp
                            <p class="text-xl font-black text-gray-900">
                                {{ $p ? sprintf('%d:%02d', intdiv($p, 60), $p % 60) : '--' }}
                                <span class="text-xs font-semibold text-gray-400">/km</span>
                            </p>
                            <p class="text-[11px] text-gray-400">Pace Rata-rata</p>
                        </div>
                        <div>
                            <p class="text-xl font-black text-gray-900">{{ $weekTotals['average_heart_rate'] ? round($weekTotals['average_heart_rate']) : '--' }} <span class="text-xs font-semibold text-gray-400">bpm</span></p>
                            <p class="text-[11px] text-gray-400">Avg HR</p>
                        </div>
                    </div>
                </x-card>

                @php
                    $categoryClasses = fn ($category) => match ($category->value) {
                        'excellent', 'very_good' => 'bg-emerald-50 text-emerald-600',
                        'good' => 'bg-sky-50 text-sky-600',
                        'moderate' => 'bg-amber-50 text-amber-600',
                        default => 'bg-rose-50 text-rose-600',
                    };
                @endphp
                <x-card>
                    <div class="flex items-center justify-between mb-3">
                        <p class="text-xs font-bold uppercase tracking-wider text-gray-400">🎯 Running Performance</p>
                        <a href="{{ route('running.pace') }}" class="text-xs font-bold text-raga-primary hover:text-raga-accent transition">Detail →</a>
                    </div>
                    @if ($performance)
                        @php $category = \App\Support\ScoreCategory::fromScore($performance['score']); @endphp
                        <div class="flex items-center justify-between">
                            <p class="text-3xl font-black text-gray-900">{{ $performance['score'] }}</p>
                            <span class="rounded-full px-3 py-1 text-xs font-bold {{ $categoryClasses($category) }}">{{ $category->label() }}</span>
                        </div>
                        <div class="mt-3 space-y-1.5">
                            @foreach ($performance['factors'] as $factor)
                                <div class="flex items-center justify-between text-xs">
                                    <span class="text-gray-500">{{ $factor['label'] }}</span>
                                    @if ($factor['insufficient_data'])
                                        <span class="text-gray-300">Belum cukup data</span>
                                    @else
                                        <span class="font-bold tabular-nums {{ $factor['contribution'] > 0 ? 'text-raga-excellent' : ($factor['contribution'] < 0 ? 'text-raga-low' : 'text-gray-400') }}">
                                            {{ $factor['contribution'] > 0 ? '+' : '' }}{{ $factor['contribution'] }}
                                        </span>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-sm text-gray-400">Belum cukup data (minimal 7 lari dalam 30 hari terakhir).</p>
                    @endif
                </x-card>

                <x-card>
                    <p class="text-xs font-bold uppercase tracking-wider text-gray-400 mb-3">🔥 Konsistensi 30 Hari</p>
                    <div class="grid grid-cols-3 gap-3">
                        <div>
                            <p class="text-xl font-black text-gray-900">{{ $consistency['consistency_percent'] }}%</p>
                            <p class="text-[11px] text-gray-400">Konsisten</p>
                        </div>
                        <div>
                            <p class="text-xl font-black text-gray-900">{{ $consistency['days_with_workout'] }}</p>
                            <p class="text-[11px] text-gray-400">Hari Lari</p>
                        </div>
                        <div>
                            <p class="text-xl font-black text-gray-900">{{ $consistency['current_streak_days'] }}</p>
                            <p class="text-[11px] text-gray-400">Streak Aktif</p>
                        </div>
                    </div>
                </x-card>

                <x-card>
                    <p class="text-xs font-bold uppercase tracking-wider text-gray-400 mb-3">📈 VO2 Max</p>
                    @if ($latestVo2max)
                        <p class="text-2xl font-black text-gray-900">{{ number_format($latestVo2max['value'], 1) }} <span class="text-sm font-semibold text-gray-400">ml/kg/min</span></p>
                        <p class="text-[11px] text-gray-400">{{ \Illuminate\Support\Carbon::parse($latestVo2max['date'])->translatedFormat('d M Y') }}</p>
                    @else
                        <p class="text-sm text-gray-400">Belum ada data VO2 Max.</p>
                    @endif
                </x-card>
            </div>

            @if ($longestRuns->isNotEmpty())
                <div>
                    <div class="mb-3 flex items-center justify-between">
                        <h3 class="text-xs font-bold uppercase tracking-wider text-gray-400">🏅 Lari Terjauh</h3>
                        <a href="{{ route('running.records') }}" class="text-xs font-bold text-raga-primary hover:text-raga-accent transition">Lihat semua →</a>
                    </div>
                    <div class="grid grid-cols-3 gap-3">
                        @foreach ($longestRuns as $run)
                            <a href="{{ route('activities.show', $run) }}">
                                <x-card class="!p-4">
                                    <p class="text-xl font-black text-gray-900">{{ number_format($run->distance_meters / 1000, 2) }} <span class="text-xs font-semibold text-gray-400">km</span></p>
                                    <p class="text-xs text-gray-400">{{ $run->start_date->translatedFormat('d M Y') }}</p>
                                </x-card>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif

            @if ($personalRecords->isNotEmpty())
                <div>
                    <div class="mb-3 flex items-center justify-between">
                        <h3 class="text-xs font-bold uppercase tracking-wider text-gray-400">🏆 Personal Records</h3>
                        <a href="{{ route('running.records') }}" class="text-xs font-bold text-raga-primary hover:text-raga-accent transition">Lihat semua →</a>
                    </div>
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                        @foreach ($personalRecords->take(6) as $pr)
                            <x-card class="!p-4">
                                <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400">{{ $pr->label() }}</p>
                                <p class="mt-1 text-xl font-black text-gray-900">{{ $pr->formattedValue() }}</p>
                                <p class="mt-0.5 text-xs text-gray-400">{{ $pr->achieved_date->translatedFormat('d M Y') }}</p>
                            </x-card>
                        @endforeach
                    </div>
                </div>
            @endif

        </div>
    </div>
</x-app-layout>
