<x-app-layout>
    <x-slot name="header">
        <h2 class="text-2xl font-extrabold text-gray-900 leading-tight">
            {{ __('Good Morning, :name 👋', ['name' => explode(' ', auth()->user()->name)[0]]) }}
        </h2>
        <p class="mt-1 text-sm font-medium text-gray-500">Bagaimana tubuh dan latihan kamu hari ini?</p>
    </x-slot>

    <div class="py-6 pb-16">
        <div class="px-4 sm:px-6 lg:px-8 space-y-8">

            {{-- 1. TODAY --}}
            <section>
                <h3 class="mb-3 text-xs font-bold uppercase tracking-wider text-gray-400">Hari Ini</h3>

                @php
                    $readinessValue = $today['readiness'];
                    [$statusLabel, $statusClasses] = match (true) {
                        $readinessValue === null => ['Belum ada data', 'bg-gray-100 text-gray-400'],
                        $readinessValue >= 75 => ['Siap', 'bg-emerald-50 text-emerald-600'],
                        $readinessValue >= 50 => ['Cukup', 'bg-amber-50 text-amber-600'],
                        default => ['Recovery', 'bg-rose-50 text-rose-600'],
                    };
                @endphp

                <a href="{{ route('recovery') }}" class="block rounded-3xl bg-white border border-gray-100 p-6 shadow-[0_1px_3px_rgba(16,24,40,0.06)] mb-3 hover:border-raga-primary/30 transition">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2.5">
                            <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-raga-accent to-raga-primary text-white text-base shadow-glow">🔋</span>
                            <p class="text-sm font-bold text-gray-500">Readiness Score</p>
                        </div>
                        <span class="rounded-full px-3 py-1 text-xs font-bold {{ $statusClasses }}">{{ $statusLabel }}</span>
                    </div>
                    <p class="mt-4 text-6xl font-black text-gray-900 tracking-tight">
                        {{ $readinessValue !== null ? round($readinessValue) : '--' }}
                    </p>
                    <p class="mt-2 text-sm text-gray-500">
                        @if ($readinessValue === null)
                            Belum ada skor readiness — buka Recovery untuk hitung sekarang.
                        @elseif ($readinessValue >= 75)
                            Kondisi bagus, siap untuk latihan lebih berat.
                        @elseif ($readinessValue >= 50)
                            Kondisi cukup, latihan moderat masih aman.
                        @else
                            Kondisi rendah, pertimbangkan recovery hari ini.
                        @endif
                        <span class="font-semibold text-raga-primary">Lihat rincian →</span>
                    </p>
                </a>

                <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                    <x-metric-tile icon="👟" label="Steps" :value="$today['steps'] !== null ? number_format((int) $today['steps']) : null" />
                    <x-metric-tile icon="❤️" label="Resting HR" :value="$today['resting_heart_rate'] !== null ? round($today['resting_heart_rate']) : null" unit="bpm" />
                    <x-metric-tile icon="🔋" label="Body Battery" :value="($today['body_battery']['charged'] !== null || $today['body_battery']['drained'] !== null) ? '+'.round($today['body_battery']['charged'] ?? 0).' / -'.round($today['body_battery']['drained'] ?? 0) : null" />
                    <x-metric-tile icon="🧠" label="Stress" :value="$today['stress'] !== null ? round($today['stress']) : null" />
                    <x-metric-tile icon="🔥" label="Training Load" :value="round($today['training_load'])" unit="kcal (est.)" />
                    <x-metric-tile icon="🥗" label="Active Calories" :value="$today['active_calories'] !== null ? number_format(round($today['active_calories'])).' kcal' : null" />
                </div>
            </section>

            {{-- 2. RECENT ACTIVITY --}}
            <section>
                <h3 class="mb-3 text-xs font-bold uppercase tracking-wider text-gray-400">Aktivitas Terakhir</h3>
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
                        <div class="flex items-center gap-3">
                            <span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-gray-50 text-xl">{{ $icon }}</span>
                            <div>
                                <p class="font-bold text-gray-900">{{ ucwords(str_replace('_', ' ', $recentWorkout->type)) }}</p>
                                <p class="text-xs text-gray-400">{{ $isToday ? 'Hari ini, '.$recentWorkout->start_date->format('H:i') : $recentWorkout->start_date->diffForHumans() }}</p>
                            </div>
                        </div>
                        <div class="mt-5 grid grid-cols-2 sm:grid-cols-4 gap-4">
                            <div>
                                <p class="text-[10px] font-bold uppercase text-gray-400">Distance</p>
                                <p class="mt-0.5 font-bold text-gray-900">{{ $recentWorkout->distance_meters ? number_format($recentWorkout->distance_meters / 1000, 2).' km' : '--' }}</p>
                            </div>
                            <div>
                                <p class="text-[10px] font-bold uppercase text-gray-400">Duration</p>
                                <p class="mt-0.5 font-bold text-gray-900">{{ intdiv($durationMin, 60) }}h {{ $durationMin % 60 }}m</p>
                            </div>
                            <div>
                                <p class="text-[10px] font-bold uppercase text-gray-400">Calories</p>
                                <p class="mt-0.5 font-bold text-gray-900">{{ $recentWorkout->active_calories ? round($recentWorkout->active_calories) : '--' }}</p>
                            </div>
                            <div>
                                <p class="text-[10px] font-bold uppercase text-gray-400">Avg HR</p>
                                <p class="mt-0.5 font-bold text-gray-900">{{ $recentWorkout->average_heart_rate ? round($recentWorkout->average_heart_rate).' bpm' : '--' }}</p>
                            </div>
                            @if ($recentWorkout->elevation_gain_meters)
                                <div>
                                    <p class="text-[10px] font-bold uppercase text-gray-400">Elevation</p>
                                    <p class="mt-0.5 font-bold text-gray-900">{{ round($recentWorkout->elevation_gain_meters) }} m</p>
                                </div>
                            @endif
                        </div>
                    @else
                        <p class="text-gray-400">Belum ada aktivitas tercatat.</p>
                    @endif
                </x-card>
            </section>

            {{-- 3. TRAINING THIS WEEK --}}
            <section>
                <h3 class="mb-3 text-xs font-bold uppercase tracking-wider text-gray-400">Training Minggu Ini</h3>
                <x-card>
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
                        <div>
                            <p class="text-[10px] font-bold uppercase text-gray-400">Total Distance</p>
                            <p class="mt-0.5 text-xl font-black text-gray-900">{{ number_format($week['total_distance_meters'] / 1000, 1) }} km</p>
                        </div>
                        <div>
                            <p class="text-[10px] font-bold uppercase text-gray-400">Total Duration</p>
                            @php $wMin = intdiv($week['total_duration_seconds'], 60); @endphp
                            <p class="mt-0.5 text-xl font-black text-gray-900">{{ intdiv($wMin, 60) }}h {{ $wMin % 60 }}m</p>
                        </div>
                        <div>
                            <p class="text-[10px] font-bold uppercase text-gray-400">Elevation</p>
                            <p class="mt-0.5 text-xl font-black text-gray-900">{{ round($week['total_elevation_meters']) }} m</p>
                        </div>
                        <div>
                            <p class="text-[10px] font-bold uppercase text-gray-400">Activities</p>
                            <p class="mt-0.5 text-xl font-black text-gray-900">{{ $week['activity_count'] }}</p>
                        </div>
                    </div>

                    @if ($week['activity_count'] > 0)
                        <x-weekly-bar-chart :series="$week['daily_series']" />
                    @else
                        <p class="text-center text-sm text-gray-400 py-6">Belum ada aktivitas minggu ini.</p>
                    @endif
                </x-card>
            </section>

            {{-- 4. GOALS --}}
            @if (count($goals) > 0)
                <section>
                    <div class="mb-3 flex items-center justify-between">
                        <h3 class="text-xs font-bold uppercase tracking-wider text-gray-400">🎯 Goals</h3>
                        <a href="{{ route('goals.index') }}" class="text-xs font-bold text-raga-primary hover:text-raga-accent transition">Kelola →</a>
                    </div>
                    <div class="grid sm:grid-cols-3 gap-3">
                        @foreach ($goals as $g)
                            @php
                                $p = $g['progress'];
                                $reached = $p['percent'] !== null && $p['percent'] >= 100;
                                $barColor = $reached ? 'bg-raga-excellent' : ($p['percent'] !== null && $p['percent'] >= 50 ? 'bg-raga-primary' : 'bg-raga-accent');
                            @endphp
                            <x-card class="!p-4">
                                <div class="flex items-center justify-between">
                                    <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400">{{ $p['label'] }}</p>
                                    <p class="text-sm font-black text-gray-900">{{ $p['percent'] !== null ? $p['percent'].'%' : '--' }}</p>
                                </div>
                                <p class="mt-1 text-sm text-gray-600">
                                    @if ($p['current'] !== null)
                                        <span class="font-bold text-gray-900">{{ $p['current_text'] }}</span> / {{ $p['target_text'] }}
                                    @else
                                        {{ $p['target_text'] }}
                                    @endif
                                </p>
                                @if ($p['percent'] !== null)
                                    <div class="mt-2 h-1.5 w-full overflow-hidden rounded-full bg-gray-100 dark:bg-gray-800">
                                        <div class="h-full rounded-full transition-all {{ $barColor }}" style="width: {{ $p['percent'] }}%"></div>
                                    </div>
                                @endif
                            </x-card>
                        @endforeach
                    </div>
                </section>
            @endif

            {{-- 5. HEALTH TREND --}}
            <section>
                <h3 class="mb-3 text-xs font-bold uppercase tracking-wider text-gray-400">Health Trend</h3>
                <x-card>
                    <x-health-trend-chart :series="$trendSeries" />
                </x-card>
            </section>

            {{-- 6. AI RECOMMENDATIONS --}}
            @if (count($recommendations) > 0)
                <section>
                    <h3 class="mb-3 text-xs font-bold uppercase tracking-wider text-gray-400">🤖 Rekomendasi AI</h3>
                    <div class="space-y-3">
                        @foreach ($recommendations as $recommendation)
                            <x-card class="!p-4">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <p class="text-[10px] font-bold uppercase text-raga-primary">{{ $recommendation->category }}</p>
                                        <p class="mt-0.5 font-bold text-gray-900">{{ $recommendation->title }}</p>
                                        <p class="mt-1 text-sm text-gray-600">{{ $recommendation->message }}</p>
                                    </div>
                                    <span class="shrink-0 text-xs text-gray-400">{{ $recommendation->date->translatedFormat('d M') }}</span>
                                </div>
                            </x-card>
                        @endforeach
                    </div>
                </section>
            @endif

            {{-- 6. INSIGHTS --}}
            <section>
                <h3 class="mb-3 text-xs font-bold uppercase tracking-wider text-gray-400">✨ Insights</h3>
                @if (count($insights) > 0)
                    <div class="space-y-3">
                        @foreach ($insights as $insight)
                            <x-card class="!p-4 flex items-start gap-3">
                                <span class="text-lg">💡</span>
                                <p class="text-sm text-gray-700">{{ $insight }}</p>
                            </x-card>
                        @endforeach
                    </div>
                @else
                    <x-card class="text-center py-8">
                        <p class="text-gray-400">Belum cukup data untuk insight yang bermakna. Terus sync Garmin kamu tiap hari.</p>
                    </x-card>
                @endif
            </section>

            <div class="flex gap-3">
                <a href="{{ route('health') }}" class="flex-1 text-center rounded-full border-2 border-gray-200 px-5 py-2.5 text-sm font-bold text-gray-600 hover:border-raga-primary hover:text-raga-primary transition">
                    Lihat Data Kesehatan →
                </a>
                <a href="{{ route('training') }}" class="flex-1 text-center rounded-full border-2 border-gray-200 px-5 py-2.5 text-sm font-bold text-gray-600 hover:border-raga-primary hover:text-raga-primary transition">
                    Lihat Training →
                </a>
            </div>
        </div>
    </div>
</x-app-layout>
