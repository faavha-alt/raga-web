@php
    $user = auth()->user();
    $firstName = explode(' ', $user->name)[0];
    $hour = (int) now()->format('G');
    $greeting = match (true) {
        $hour < 11 => 'Selamat Pagi',
        $hour < 15 => 'Selamat Siang',
        $hour < 18 => 'Selamat Sore',
        default => 'Selamat Malam',
    };

    // Kondisi fisiologis hari ini: readiness adalah skor gabungan yang sudah
    // dihitung engine RAGA (bukan angka Garmin).
    $readiness = $scores['readiness'];
    [$stateLabel, $stateTone] = match (true) {
        $readiness === null => ['BELUM ADA DATA', 'text-telemetry-slate'],
        $readiness >= 75 => ['KUAT', 'text-telemetry-emerald-deep'],
        $readiness >= 50 => ['CUKUP', 'text-telemetry-amber'],
        default => ['PEMULIHAN', 'text-telemetry-ember-deep'],
    };

    $loadRisk = $scores['load_risk'];
    [$loadLabel, $loadChip] = match ($loadRisk) {
        'undertraining' => ['Undertraining', 'neutral'],
        'optimal' => ['Optimal', 'recovery'],
        'caution' => ['Waspada', 'neutral'],
        'high_risk' => ['Beban Tinggi', 'strain'],
        default => ['Belum ada data', 'neutral'],
    };

    // Grafik puncak: Relative Effort harian; kalau belum ada HR sama sekali,
    // jatuh ke jarak harian supaya kartunya tidak kosong.
    $effortTotal = array_sum(array_column($peaks, 'relative_effort'));
    $useEffort = $effortTotal > 0;
    $peaksSeries = collect($peaks)->map(fn (array $day): array => [
        'label' => $day['label'],
        'value' => $useEffort ? $day['relative_effort'] : round($day['distance_meters'] / 1000, 1),
    ])->all();
    $peaksUnit = $useEffort ? 'Relative Effort' : 'km';
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="telemetry-value text-4xl sm:text-5xl">{{ mb_strtoupper($greeting) }}, {{ mb_strtoupper($firstName) }}</h1>
                <p class="mt-2 text-sm font-medium text-telemetry-slate">
                    Jalur atletikmu • status hari ini <span class="font-bold {{ $stateTone }}">{{ mb_strtolower($stateLabel) }}</span>
                </p>
            </div>

            @if ($garmin)
                <div class="flex items-center gap-2 border border-telemetry-line bg-white px-3 py-1.5">
                    <span class="h-1.5 w-1.5 rounded-full {{ $garmin->last_sync_status === 'success' ? 'bg-telemetry-emerald' : 'bg-telemetry-amber' }}"></span>
                    <span class="telemetry-label">Garmin Connect</span>
                    <span class="text-[11px] font-semibold text-telemetry-ink">
                        {{ $garmin->last_synced_at?->diffForHumans() ?? 'belum pernah sync' }}
                    </span>
                </div>
            @endif
        </div>
    </x-slot>

    <div class="py-6 pb-16">
        <div class="space-y-6 px-4 sm:px-6 lg:px-8">

            {{-- 1. BEBAN MINGGU INI + PUNCAK 7 HARI --}}
            <section class="grid gap-4 lg:grid-cols-5">
                <x-card class="lg:col-span-3">
                    <x-section-heading title="Minggu Ini // Beban Aerobik" hint="Senin – Hari Ini" />

                    @if ($week['activity_count'] > 0)
                        <p class="telemetry-value text-6xl leading-none">
                            {{ number_format($week['total_distance_meters'] / 1000, 1) }}<span class="ml-2 text-base font-bold uppercase tracking-[0.08em] text-telemetry-slate">km</span>
                        </p>

                        @php $weekMinutes = intdiv($week['total_duration_seconds'], 60); @endphp
                        <div class="mt-6 grid grid-cols-3 gap-4 border-t border-telemetry-line pt-4">
                            <div>
                                <p class="telemetry-label">Aktivitas</p>
                                <p class="mt-1 telemetry-value text-2xl">{{ $week['activity_count'] }}</p>
                            </div>
                            <div>
                                <p class="telemetry-label">Durasi</p>
                                <p class="mt-1 telemetry-value text-2xl">{{ intdiv($weekMinutes, 60) }}<span class="text-sm">h</span> {{ $weekMinutes % 60 }}<span class="text-sm">m</span></p>
                            </div>
                            <div>
                                <p class="telemetry-label">Elevasi</p>
                                <p class="mt-1 telemetry-value text-2xl">{{ number_format($week['total_elevation_meters']) }}<span class="text-sm">m</span></p>
                            </div>
                        </div>

                        <div class="mt-5">
                            <x-weekly-bar-chart :series="$week['daily_series']" />
                        </div>
                    @else
                        <p class="mt-4 text-sm text-telemetry-slate">Belum ada aktivitas minggu ini.</p>
                    @endif
                </x-card>

                <x-card class="lg:col-span-2">
                    <x-section-heading title="7 Hari Terakhir // Puncak Beban" :hint="mb_strtoupper($peaksUnit)" />
                    <x-telemetry-peaks-chart
                        :series="$peaksSeries"
                        :caption="$useEffort
                            ? 'Relative Effort harian — dihitung RAGA dari zona HR kamu sendiri.'
                            : 'Jarak harian (km) — belum ada aktivitas dengan HR per-detik untuk menghitung Relative Effort.'"
                    />
                </x-card>
            </section>

            {{-- 2. KONDISI FISIOLOGIS --}}
            <x-card>
                <x-section-heading title="Kondisi Fisiologis" hint="Model 30 Hari Terakhir" />

                <div class="mb-5 flex flex-wrap items-baseline gap-x-3 gap-y-1 border-b border-telemetry-line pb-4">
                    <span class="telemetry-label">Kondisi saat ini</span>
                    <span class="telemetry-value text-2xl {{ $stateTone }}">{{ $stateLabel }}</span>
                    <span class="telemetry-value text-2xl">
                        ({{ $readiness !== null ? round($readiness) : '--' }}<span class="text-sm text-telemetry-slate">/100</span>)
                    </span>
                </div>

                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <x-metric-tile label="Recovery" :value="$scores['recovery'] !== null ? round($scores['recovery']) : null" />

                    <div class="border border-telemetry-line bg-telemetry-surface p-4">
                        <div class="flex items-center justify-between">
                            <p class="telemetry-label">Readiness</p>
                            <x-chip :variant="$readiness !== null && $readiness >= 75 ? 'recovery' : 'neutral'">{{ $stateLabel }}</x-chip>
                        </div>
                        <p class="mt-2.5 text-[28px] leading-none telemetry-value">{{ $readiness !== null ? round($readiness) : '--' }}</p>
                    </div>

                    <div class="border border-telemetry-line bg-telemetry-surface p-4">
                        <div class="flex items-center justify-between">
                            <p class="telemetry-label">Konsistensi</p>
                            <x-chip variant="pace">{{ $scores['consistency_streak'] }} hari beruntun</x-chip>
                        </div>
                        <p class="mt-2.5 text-[28px] leading-none telemetry-value">
                            {{ $scores['consistency_percent'] !== null ? round($scores['consistency_percent']) : '--' }}<span class="ml-1.5 text-[10px] font-semibold uppercase tracking-[0.08em] text-telemetry-slate">%</span>
                        </p>
                    </div>

                    <div class="border border-telemetry-line bg-telemetry-surface p-4">
                        <div class="flex items-center justify-between">
                            <p class="telemetry-label">Beban (ACWR)</p>
                            <x-chip :variant="$loadChip">{{ $loadLabel }}</x-chip>
                        </div>
                        <p class="mt-2.5 text-[28px] leading-none telemetry-value">
                            {{ $scores['load_ratio'] !== null ? number_format($scores['load_ratio'], 2) : '--' }}
                        </p>
                    </div>
                </div>

                <p class="mt-4 text-[11px] text-telemetry-slate">
                    Recovery, readiness, dan konsistensi dihitung RAGA dari data kamu sendiri (baseline 30 hari).
                    ACWR = beban 7 hari dibanding 28 hari.
                    @if ($readiness === null)
                        Belum ada skor readiness — <a href="{{ route('recovery') }}" class="font-semibold text-telemetry-ember-deep hover:underline">buka Recovery untuk menghitung sekarang</a>.
                    @endif
                </p>
            </x-card>

            {{-- 3. ATHLETE COGNITIVE ENGINE // INSIGHTS --}}
            <section>
                <x-section-heading title="Athlete Cognitive Engine" hint="Berbasis Data Kamu" />

                @if (count($insights) > 0)
                    <div class="grid gap-4 md:grid-cols-2">
                        @foreach ($insights as $index => $insight)
                            <x-card>
                                <div class="flex items-start justify-between gap-3">
                                    <p class="telemetry-label">Insight {{ str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) }}</p>
                                    <x-chip variant="neutral">Data</x-chip>
                                </div>
                                <p class="mt-3 text-sm leading-relaxed text-telemetry-ink">{{ $insight }}</p>
                                <a href="{{ route('training') }}" class="mt-4 inline-flex items-center gap-1 font-display text-[11px] font-bold uppercase tracking-[0.08em] text-telemetry-ember-deep hover:underline">
                                    Buka Training →
                                </a>
                            </x-card>
                        @endforeach
                    </div>
                @else
                    <x-card class="py-8 text-center">
                        <p class="text-telemetry-slate">Belum cukup data untuk insight yang bermakna. Terus sync Garmin kamu tiap hari.</p>
                    </x-card>
                @endif
            </section>

            {{-- 4. SESI KRONOLOGIS --}}
            <section>
                <x-section-heading title="Sesi Kronologis" :hint="$recentSessions->count() > 0 ? 'Terbaru lebih dulu' : null" />

                @if ($recentSessions->isNotEmpty())
                    @php
                        $latest = $recentSessions->first();
                        $latestMinutes = intdiv($latest->durationSeconds(), 60);
                        $latestPace = $latest->average_pace_seconds_per_km;
                        $latestIcon = match (true) {
                            str_contains((string) $latest->type, 'run') || $latest->type === 'walking' => '🏃',
                            str_contains((string) $latest->type, 'bik') || str_contains((string) $latest->type, 'cycl') => '🚴',
                            str_contains((string) $latest->type, 'swim') => '🏊',
                            str_contains((string) $latest->type, 'strength') => '🏋️',
                            default => '💪',
                        };
                    @endphp

                    <x-card>
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="flex items-center gap-3">
                                <span class="flex h-11 w-11 items-center justify-center border border-telemetry-line bg-telemetry-well text-xl">{{ $latestIcon }}</span>
                                <div>
                                    <p class="telemetry-label">{{ $latest->start_date->isToday() ? 'Hari ini' : $latest->start_date->translatedFormat('D, d M') }} // {{ $latest->start_date->format('H:i') }}</p>
                                    <h3 class="telemetry-value text-xl">{{ $latest->name ?: ucwords(str_replace('_', ' ', (string) $latest->type)) }}</h3>
                                </div>
                            </div>
                            <a href="{{ route('activities.show', $latest) }}" class="font-display text-[11px] font-bold uppercase tracking-[0.08em] text-telemetry-ember-deep hover:underline">
                                Lihat aktivitas →
                            </a>
                        </div>

                        <div class="mt-5 grid grid-cols-2 gap-4 border-t border-telemetry-line pt-4 sm:grid-cols-3 lg:grid-cols-5">
                            <div>
                                <p class="telemetry-label">Jarak</p>
                                <p class="mt-1 telemetry-value text-xl">{{ $latest->distance_meters ? number_format($latest->distance_meters / 1000, 2).' km' : '--' }}</p>
                            </div>
                            <div>
                                <p class="telemetry-label">Durasi</p>
                                <p class="mt-1 telemetry-value text-xl">{{ intdiv($latestMinutes, 60) }}h {{ $latestMinutes % 60 }}m</p>
                            </div>
                            <div>
                                <p class="telemetry-label">Pace</p>
                                <p class="mt-1 telemetry-value text-xl">{{ $latestPace ? sprintf('%d:%02d /km', intdiv((int) $latestPace, 60), (int) $latestPace % 60) : '--' }}</p>
                            </div>
                            <div>
                                <p class="telemetry-label">Elevasi</p>
                                <p class="mt-1 telemetry-value text-xl">{{ $latest->elevation_gain_meters ? round($latest->elevation_gain_meters).' m' : '--' }}</p>
                            </div>
                            <div>
                                <p class="telemetry-label">Relative Effort</p>
                                <p class="mt-1 telemetry-value text-xl">{{ $latest->relative_effort ?? '--' }}</p>
                            </div>
                        </div>
                    </x-card>

                    @if ($recentSessions->count() > 1)
                        <div class="mt-4 grid gap-4 md:grid-cols-2">
                            @foreach ($recentSessions->slice(1) as $session)
                                @php $sessionMinutes = intdiv($session->durationSeconds(), 60); @endphp
                                <x-card class="!p-4">
                                    <div class="flex items-center justify-between gap-3">
                                        <div class="min-w-0">
                                            <p class="telemetry-label">{{ $session->start_date->translatedFormat('D, d M') }}</p>
                                            <p class="mt-1 truncate text-sm font-bold text-telemetry-ink">{{ $session->name ?: ucwords(str_replace('_', ' ', (string) $session->type)) }}</p>
                                        </div>
                                        <x-chip variant="neutral">{{ $session->type }}</x-chip>
                                    </div>
                                    <div class="mt-3 flex flex-wrap gap-x-5 gap-y-2 border-t border-telemetry-line pt-3">
                                        <span class="telemetry-value text-sm">{{ $session->distance_meters ? number_format($session->distance_meters / 1000, 2).' km' : '--' }}</span>
                                        <span class="telemetry-value text-sm">{{ intdiv($sessionMinutes, 60) }}h {{ $sessionMinutes % 60 }}m</span>
                                        <span class="telemetry-value text-sm">RE {{ $session->relative_effort ?? '--' }}</span>
                                    </div>
                                </x-card>
                            @endforeach
                        </div>
                    @endif
                @else
                    <x-card>
                        <p class="text-telemetry-slate">Belum ada aktivitas tercatat.</p>
                    </x-card>
                @endif
            </section>

            {{-- 5. GOALS --}}
            @if (count($goals) > 0)
                <section>
                    <div class="mb-3 flex items-end justify-between gap-4">
                        <h3 class="telemetry-label-lg text-telemetry-ink">Goals Aktif</h3>
                        <a href="{{ route('goals.index') }}" class="font-display text-[11px] font-bold uppercase tracking-[0.08em] text-telemetry-ember-deep hover:underline">Kelola →</a>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-3">
                        @foreach ($goals as $g)
                            @php
                                $p = $g['progress'];
                                $reached = $p['percent'] !== null && $p['percent'] >= 100;
                            @endphp
                            <x-card class="!p-4">
                                <div class="flex items-center justify-between">
                                    <p class="telemetry-label">{{ $p['label'] }}</p>
                                    <p class="telemetry-value text-sm">{{ $p['percent'] !== null ? $p['percent'].'%' : '--' }}</p>
                                </div>
                                <p class="mt-2 text-sm text-telemetry-slate">
                                    @if ($p['current'] !== null)
                                        <span class="font-bold text-telemetry-ink">{{ $p['current_text'] }}</span> / {{ $p['target_text'] }}
                                    @else
                                        {{ $p['target_text'] }}
                                    @endif
                                </p>
                                @if ($p['percent'] !== null)
                                    <div class="mt-2 h-1 w-full bg-telemetry-well">
                                        <div class="h-full {{ $reached ? 'bg-telemetry-emerald' : 'bg-telemetry-ink' }}" style="width: {{ min(100, $p['percent']) }}%"></div>
                                    </div>
                                @endif
                            </x-card>
                        @endforeach
                    </div>
                </section>
            @endif

            {{-- 6. BIOMETRIK HARI INI --}}
            <section>
                <x-section-heading title="Hari Ini // Biometrik" hint="Sumber: Garmin Connect" />
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
                    <x-metric-tile label="Steps" :value="$today['steps'] !== null ? number_format((int) $today['steps']) : null" />
                    <x-metric-tile label="Resting HR" :value="$today['resting_heart_rate'] !== null ? round($today['resting_heart_rate']) : null" unit="bpm" />
                    <x-metric-tile label="Body Battery" :value="($today['body_battery']['charged'] !== null || $today['body_battery']['drained'] !== null) ? '+'.round($today['body_battery']['charged'] ?? 0).'/−'.round($today['body_battery']['drained'] ?? 0) : null" />
                    <x-metric-tile label="Stress" :value="$today['stress'] !== null ? round($today['stress']) : null" />
                    <x-metric-tile label="Training Load" :value="round($today['training_load'])" />
                    <x-metric-tile label="Active Calories" :value="$today['active_calories'] !== null ? number_format(round($today['active_calories'])) : null" unit="kcal" />
                </div>
            </section>

            {{-- 7. HEALTH TREND --}}
            <section>
                <x-section-heading title="Tren Kesehatan" hint="7 / 30 / 90 Hari" />
                <x-card>
                    <x-health-trend-chart :series="$trendSeries" />
                </x-card>
            </section>

            {{-- 8. REKOMENDASI AI --}}
            @if (count($recommendations) > 0)
                <section>
                    <x-section-heading title="Rekomendasi AI" hint="Belum Dibaca" />
                    <div class="space-y-3">
                        @foreach ($recommendations as $recommendation)
                            <x-card class="!p-4">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <p class="telemetry-label text-telemetry-ember-deep">{{ $recommendation->category }}</p>
                                        <p class="mt-1 text-sm font-bold text-telemetry-ink">{{ $recommendation->title }}</p>
                                        <p class="mt-1 text-sm text-telemetry-slate">{{ $recommendation->message }}</p>
                                    </div>
                                    <span class="shrink-0 telemetry-label">{{ $recommendation->date->translatedFormat('d M') }}</span>
                                </div>
                            </x-card>
                        @endforeach
                    </div>
                </section>
            @endif

            {{-- 9. AKSI CEPAT --}}
            <div class="flex gap-3">
                <a href="{{ route('health') }}" class="flex-1 border border-telemetry-line bg-white px-5 py-2.5 text-center font-display text-[11px] font-bold uppercase tracking-[0.08em] text-telemetry-ink transition-colors hover:bg-telemetry-well">
                    Lihat Data Kesehatan →
                </a>
                <a href="{{ route('training') }}" class="flex-1 border border-telemetry-line bg-white px-5 py-2.5 text-center font-display text-[11px] font-bold uppercase tracking-[0.08em] text-telemetry-ink transition-colors hover:bg-telemetry-well">
                    Lihat Training →
                </a>
            </div>
        </div>
    </div>
</x-app-layout>
