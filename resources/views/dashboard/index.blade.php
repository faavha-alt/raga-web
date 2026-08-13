<x-app-layout>
    <x-slot name="header">
        <h2 class="text-2xl font-extrabold text-gray-900 dark:text-white leading-tight">
            {{ __('Good Morning, :name 👋', ['name' => explode(' ', auth()->user()->name)[0]]) }}
        </h2>
        <p class="mt-1 text-sm font-medium text-gray-500 dark:text-gray-400">Bagaimana tubuh dan latihan kamu hari ini?</p>
    </x-slot>

    <div class="py-6 pb-16">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-8">

            {{-- 1. TODAY --}}
            <section>
                <h3 class="mb-3 text-xs font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500">Hari Ini</h3>

                <x-card class="bg-gradient-to-br from-raga-accent/5 to-raga-primary/5 border-raga-primary/10 mb-3">
                    <p class="text-xs font-bold uppercase tracking-wider text-raga-primary">🔋 Readiness</p>
                    <p class="mt-1 text-5xl font-black text-gray-900 dark:text-gray-100">
                        {{ $today['readiness'] !== null ? round($today['readiness']) : '--' }}
                    </p>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        @if ($today['readiness'] === null)
                            Belum ada data readiness dari Garmin.
                        @elseif ($today['readiness'] >= 75)
                            Kondisi bagus, siap untuk latihan lebih berat.
                        @elseif ($today['readiness'] >= 50)
                            Kondisi cukup, latihan moderat masih aman.
                        @else
                            Kondisi rendah, pertimbangkan recovery hari ini.
                        @endif
                    </p>
                </x-card>

                <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                    <x-metric-tile icon="😴" label="Sleep" :value="$today['sleep']['minutes'] ? intdiv((int) $today['sleep']['minutes'], 60).'h '.((int) $today['sleep']['minutes'] % 60).'m' : null" />
                    <x-metric-tile icon="💓" label="HRV" :value="$today['hrv'] !== null ? round($today['hrv']) : null" unit="ms" />
                    <x-metric-tile icon="❤️" label="Resting HR" :value="$today['resting_heart_rate'] !== null ? round($today['resting_heart_rate']) : null" unit="bpm" />
                    <x-metric-tile icon="🔋" label="Body Battery" :value="($today['body_battery']['charged'] !== null || $today['body_battery']['drained'] !== null) ? '+'.round($today['body_battery']['charged'] ?? 0).' / -'.round($today['body_battery']['drained'] ?? 0) : null" />
                    <x-metric-tile icon="🧠" label="Stress" :value="$today['stress'] !== null ? round($today['stress']) : null" />
                    <x-metric-tile icon="🔥" label="Training Load" :value="round($today['training_load'])" unit="kcal (est.)" />
                </div>
            </section>

            {{-- 2. RECENT ACTIVITY --}}
            <section>
                <h3 class="mb-3 text-xs font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500">Aktivitas Terakhir</h3>
                <x-card>
                    @if ($recentWorkout)
                        @php
                            $isToday = $recentWorkout->start_date->isToday();
                            $icon = match (true) {
                                str_contains($recentWorkout->type, 'run') || $recentWorkout->type === 'walking' => '🏃',
                                str_contains($recentWorkout->type, 'bik') || str_contains($recentWorkout->type, 'cycl') => '🚴',
                                str_contains($recentWorkout->type, 'swim') => '🏊',
                                str_contains($recentWorkout->type, 'strength') => '🏋️',
                                default => '💪',
                            };
                            $durationMin = intdiv($recentWorkout->durationSeconds(), 60);
                        @endphp
                        <div class="flex items-start justify-between">
                            <div class="flex items-center gap-3">
                                <span class="text-2xl">{{ $icon }}</span>
                                <div>
                                    <p class="font-bold text-gray-900 dark:text-gray-100">{{ ucwords(str_replace('_', ' ', $recentWorkout->type)) }}</p>
                                    <p class="text-xs text-gray-400">{{ $isToday ? 'Hari ini, '.$recentWorkout->start_date->format('H:i') : $recentWorkout->start_date->diffForHumans() }}</p>
                                </div>
                            </div>
                        </div>
                        <div class="mt-4 grid grid-cols-2 sm:grid-cols-4 gap-4">
                            <div>
                                <p class="text-[10px] font-bold uppercase text-gray-400">Distance</p>
                                <p class="font-bold text-gray-900 dark:text-gray-100">{{ $recentWorkout->distance_meters ? number_format($recentWorkout->distance_meters / 1000, 2).' km' : '--' }}</p>
                            </div>
                            <div>
                                <p class="text-[10px] font-bold uppercase text-gray-400">Duration</p>
                                <p class="font-bold text-gray-900 dark:text-gray-100">{{ intdiv($durationMin, 60) }}h {{ $durationMin % 60 }}m</p>
                            </div>
                            <div>
                                <p class="text-[10px] font-bold uppercase text-gray-400">Calories</p>
                                <p class="font-bold text-gray-900 dark:text-gray-100">{{ $recentWorkout->active_calories ? round($recentWorkout->active_calories) : '--' }}</p>
                            </div>
                            <div>
                                <p class="text-[10px] font-bold uppercase text-gray-400">Avg HR</p>
                                <p class="font-bold text-gray-900 dark:text-gray-100">{{ $recentWorkout->average_heart_rate ? round($recentWorkout->average_heart_rate).' bpm' : '--' }}</p>
                            </div>
                            @if ($recentWorkout->elevation_gain_meters)
                                <div>
                                    <p class="text-[10px] font-bold uppercase text-gray-400">Elevation</p>
                                    <p class="font-bold text-gray-900 dark:text-gray-100">{{ round($recentWorkout->elevation_gain_meters) }} m</p>
                                </div>
                            @endif
                        </div>
                    @else
                        <p class="text-gray-400 dark:text-gray-500">Belum ada aktivitas tercatat.</p>
                    @endif
                </x-card>
            </section>

            {{-- 3. TRAINING THIS WEEK --}}
            <section>
                <h3 class="mb-3 text-xs font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500">Training Minggu Ini</h3>
                <x-card>
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
                        <div>
                            <p class="text-[10px] font-bold uppercase text-gray-400">Total Distance</p>
                            <p class="text-xl font-black text-gray-900 dark:text-gray-100">{{ number_format($week['total_distance_meters'] / 1000, 1) }} km</p>
                        </div>
                        <div>
                            <p class="text-[10px] font-bold uppercase text-gray-400">Total Duration</p>
                            @php $wMin = intdiv($week['total_duration_seconds'], 60); @endphp
                            <p class="text-xl font-black text-gray-900 dark:text-gray-100">{{ intdiv($wMin, 60) }}h {{ $wMin % 60 }}m</p>
                        </div>
                        <div>
                            <p class="text-[10px] font-bold uppercase text-gray-400">Elevation</p>
                            <p class="text-xl font-black text-gray-900 dark:text-gray-100">{{ round($week['total_elevation_meters']) }} m</p>
                        </div>
                        <div>
                            <p class="text-[10px] font-bold uppercase text-gray-400">Activities</p>
                            <p class="text-xl font-black text-gray-900 dark:text-gray-100">{{ $week['activity_count'] }}</p>
                        </div>
                    </div>

                    @if ($week['activity_count'] > 0)
                        <x-weekly-bar-chart :series="$week['daily_series']" />
                    @else
                        <p class="text-center text-sm text-gray-400 py-6">Belum ada aktivitas minggu ini.</p>
                    @endif
                </x-card>
            </section>

            {{-- 4. HEALTH TREND --}}
            <section>
                <h3 class="mb-3 text-xs font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500">Health Trend</h3>
                <x-card>
                    <x-health-trend-chart :series="$trendSeries" />
                </x-card>
            </section>

            {{-- 5. INSIGHTS --}}
            <section>
                <h3 class="mb-3 text-xs font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500">✨ Insights</h3>
                @if (count($insights) > 0)
                    <div class="space-y-3">
                        @foreach ($insights as $insight)
                            <x-card class="!p-4 flex items-start gap-3">
                                <span class="text-lg">💡</span>
                                <p class="text-sm text-gray-700 dark:text-gray-200">{{ $insight }}</p>
                            </x-card>
                        @endforeach
                    </div>
                @else
                    <x-card class="text-center py-8">
                        <p class="text-gray-400 dark:text-gray-500">Belum cukup data untuk insight yang bermakna. Terus sync Garmin kamu tiap hari.</p>
                    </x-card>
                @endif
            </section>

            <div class="flex gap-3">
                <a href="{{ route('health') }}" class="flex-1 text-center rounded-full border-2 border-gray-200 dark:border-gray-700 px-5 py-2.5 text-sm font-bold text-gray-600 dark:text-gray-300 hover:border-raga-primary hover:text-raga-primary transition">
                    Lihat Data Kesehatan →
                </a>
                <a href="{{ route('training') }}" class="flex-1 text-center rounded-full border-2 border-gray-200 dark:border-gray-700 px-5 py-2.5 text-sm font-bold text-gray-600 dark:text-gray-300 hover:border-raga-primary hover:text-raga-primary transition">
                    Lihat Training →
                </a>
            </div>
        </div>
    </div>
</x-app-layout>
