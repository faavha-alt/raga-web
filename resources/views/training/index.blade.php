<x-app-layout>
    <x-slot name="header">
        <h2 class="text-2xl font-extrabold text-gray-900 dark:text-white leading-tight">
            {{ __('Training') }}
        </h2>
    </x-slot>

    <div class="py-6 pb-16">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            <div class="grid sm:grid-cols-2 gap-4">
                <x-card>
                    <div class="flex items-center justify-between mb-3">
                        <p class="text-xs font-bold uppercase tracking-wider text-gray-400">📊 Volume 7 Hari</p>
                        <a href="{{ route('training.volume') }}" class="text-xs font-bold text-raga-primary hover:text-raga-accent transition">Detail →</a>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <p class="text-xl font-black text-gray-900">{{ number_format($weekTotals['distance_meters'] / 1000, 1) }} <span class="text-xs font-semibold text-gray-400">km</span></p>
                            <p class="text-[11px] text-gray-400">Jarak</p>
                        </div>
                        <div>
                            @php $durMin = intdiv($weekTotals['duration_seconds'], 60); @endphp
                            <p class="text-xl font-black text-gray-900">{{ intdiv($durMin, 60) }}h {{ $durMin % 60 }}m</p>
                            <p class="text-[11px] text-gray-400">Durasi</p>
                        </div>
                        <div>
                            <p class="text-xl font-black text-gray-900">{{ number_format($weekTotals['elevation_gain_meters']) }} <span class="text-xs font-semibold text-gray-400">m</span></p>
                            <p class="text-[11px] text-gray-400">Elevasi</p>
                        </div>
                        <div>
                            <p class="text-xl font-black text-gray-900">{{ $weekTotals['activity_count'] }}</p>
                            <p class="text-[11px] text-gray-400">Aktivitas</p>
                        </div>
                    </div>
                </x-card>

                @php
                    $riskLabels = [
                        'undertraining' => 'Undertraining', 'optimal' => 'Optimal', 'caution' => 'Waspada',
                        'high_risk' => 'Risiko Tinggi', 'insufficient_data' => 'Data Belum Cukup',
                    ];
                    $riskClasses = [
                        'undertraining' => 'bg-sky-50 text-sky-600', 'optimal' => 'bg-emerald-50 text-emerald-600',
                        'caution' => 'bg-amber-50 text-amber-600', 'high_risk' => 'bg-rose-50 text-rose-600',
                        'insufficient_data' => 'bg-gray-100 text-gray-400',
                    ];
                @endphp
                <x-card>
                    <div class="flex items-center justify-between mb-3">
                        <p class="text-xs font-bold uppercase tracking-wider text-gray-400">🎯 Training Status</p>
                        <a href="{{ route('training.load') }}" class="text-xs font-bold text-raga-primary hover:text-raga-accent transition">Detail →</a>
                    </div>
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-2xl font-black text-gray-900">{{ $status->acute_chronic_ratio !== null ? number_format($status->acute_chronic_ratio, 2) : '--' }}</p>
                            <p class="text-[11px] text-gray-400">Acute:Chronic Ratio</p>
                        </div>
                        <span class="rounded-full px-3 py-1 text-xs font-bold {{ $riskClasses[$status->risk_level] }}">{{ $riskLabels[$status->risk_level] }}</span>
                    </div>
                </x-card>

                <x-card>
                    <div class="flex items-center justify-between mb-3">
                        <p class="text-xs font-bold uppercase tracking-wider text-gray-400">🔥 Konsistensi 30 Hari</p>
                        <a href="{{ route('training.calendar') }}" class="text-xs font-bold text-raga-primary hover:text-raga-accent transition">Detail →</a>
                    </div>
                    <div class="grid grid-cols-3 gap-3">
                        <div>
                            <p class="text-xl font-black text-gray-900">{{ $consistency['consistency_percent'] }}%</p>
                            <p class="text-[11px] text-gray-400">Konsisten</p>
                        </div>
                        <div>
                            <p class="text-xl font-black text-gray-900">{{ $consistency['rest_days'] }}</p>
                            <p class="text-[11px] text-gray-400">Rest Days</p>
                        </div>
                        <div>
                            <p class="text-xl font-black text-gray-900">{{ $consistency['current_streak_days'] }}</p>
                            <p class="text-[11px] text-gray-400">Streak Aktif</p>
                        </div>
                    </div>
                </x-card>

                <x-card>
                    <div class="flex items-center justify-between mb-3">
                        <p class="text-xs font-bold uppercase tracking-wider text-gray-400">📈 Distribusi Aktivitas</p>
                        <a href="{{ route('training.distribution') }}" class="text-xs font-bold text-raga-primary hover:text-raga-accent transition">Detail →</a>
                    </div>
                    @forelse ($topTypes as $type)
                        <div class="flex items-center justify-between text-sm py-1">
                            <span class="text-gray-600">{{ $type['icon'] }} {{ $type['label'] }}</span>
                            <span class="font-bold text-gray-900">{{ $type['percent'] }}%</span>
                        </div>
                    @empty
                        <p class="text-sm text-gray-400">Belum ada aktivitas 30 hari terakhir.</p>
                    @endforelse
                </x-card>
            </div>

            <div>
                <div class="mb-3 flex items-center justify-between">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500">🗓️ Training Plan</h3>
                    <a href="{{ route('training.calendar') }}" class="text-xs font-bold text-raga-primary hover:text-raga-accent transition">Lihat Kalender →</a>
                </div>

                @if ($plans->isEmpty())
                    <x-card class="text-center py-10">
                        <p class="text-lg font-bold text-gray-900 dark:text-gray-100">Belum Ada Training Plan</p>
                        <p class="mt-2 text-gray-500 dark:text-gray-400">Buat training plan untuk lihat jadwal mingguan kamu di sini.</p>
                    </x-card>
                @else
                    <div class="space-y-4">
                        @foreach ($plans as $plan)
                            <a href="{{ route('training.calendar') }}">
                                <x-card class="hover:border-raga-primary/30 transition">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <p class="font-bold text-gray-900 dark:text-gray-100">{{ $plan->name }}</p>
                                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                                {{ ucfirst($plan->status) }} · {{ $plan->start_date->translatedFormat('d M') }} – {{ $plan->target_date->translatedFormat('d M Y') }}
                                            </p>
                                        </div>
                                        <span class="text-raga-primary text-sm font-bold">→</span>
                                    </div>
                                </x-card>
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>

            @if ($personalRecords->isNotEmpty())
                <div>
                    <h3 class="mb-3 text-xs font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500">🏆 Personal Records</h3>
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                        @foreach ($personalRecords as $pr)
                            <x-card class="!p-4">
                                <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400">{{ $pr->label() }}</p>
                                <p class="mt-1 text-xl font-black text-gray-900 dark:text-gray-100">{{ $pr->formattedValue() }}</p>
                                <p class="mt-0.5 text-xs text-gray-400">{{ $pr->achieved_date->translatedFormat('d M Y') }}</p>
                            </x-card>
                        @endforeach
                    </div>
                </div>
            @endif

            <div>
                <div class="mb-3 flex items-center justify-between">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500">📋 Aktivitas Terakhir</h3>
                    <a href="{{ route('activities') }}" class="text-xs font-bold text-raga-primary hover:text-raga-accent transition">Lihat semua →</a>
                </div>
                <x-card class="!p-0 divide-y divide-gray-100 dark:divide-gray-700 overflow-hidden">
                    @forelse ($recentWorkouts as $workout)
                        @php
                            $icon = \App\Support\ActivityTypeIcon::icon($workout->type);
                            $pace = $workout->average_pace_seconds_per_km ? (int) round($workout->average_pace_seconds_per_km) : null;
                        @endphp
                        <a href="{{ route('activities.show', $workout) }}" class="flex items-center justify-between px-5 py-3.5 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition">
                            <div class="flex items-center gap-3">
                                <span class="text-xl">{{ $icon }}</span>
                                <div>
                                    <p class="text-sm font-bold text-gray-900 dark:text-gray-100">{{ \App\Support\ActivityTypeIcon::label($workout->type) }}</p>
                                    <p class="text-xs text-gray-400">{{ $workout->start_date->translatedFormat('d M Y, H:i') }}</p>
                                </div>
                            </div>
                            <div class="text-right text-sm">
                                @if ($workout->distance_meters)
                                    <p class="font-bold text-gray-900 dark:text-gray-100">{{ number_format($workout->distance_meters / 1000, 2) }} km</p>
                                @endif
                                <p class="text-xs text-gray-400">
                                    @if ($pace) {{ sprintf('%d:%02d', intdiv($pace, 60), $pace % 60) }}/km @endif
                                    @if ($workout->average_heart_rate) · {{ round($workout->average_heart_rate) }} bpm @endif
                                </p>
                            </div>
                        </a>
                    @empty
                        <div class="px-5 py-6 text-center text-gray-400">Belum ada aktivitas</div>
                    @endforelse
                </x-card>
            </div>

        </div>
    </div>
</x-app-layout>
