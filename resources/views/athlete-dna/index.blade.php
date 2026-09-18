@php
    $user = auth()->user();

    $summaryDays = $summary['days'];
    $summaryMinutes = intdiv($summary['duration_seconds'], 60);
    $summaryDuration = $summaryMinutes >= 60
        ? sprintf('%dh %02dm', intdiv($summaryMinutes, 60), $summaryMinutes % 60)
        : sprintf('%dm', $summaryMinutes);

    $pace = $summary['average_pace_seconds_per_km'];
    $paceText = $pace !== null
        ? sprintf('%d:%02d', intdiv((int) round($pace), 60), (int) round($pace) % 60)
        : null;

    $hasAnyPillar = collect($pillars)->contains(fn (array $pillar): bool => $pillar['score'] !== null);
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="telemetry-value text-2xl sm:text-3xl lg:text-5xl">ATHLETE DNA</h1>
                <p class="mt-1 text-sm font-medium text-telemetry-slate">
                    Ringkasan analitik personal jangka panjang — semua angka dihitung dari datamu sendiri.
                </p>
            </div>
            <p class="telemetry-label">{{ now()->translatedFormat('d M Y') }} // RAGA</p>
        </div>
    </x-slot>

    <div class="py-4 sm:py-6 pb-16">
        <div class="space-y-3 sm:space-y-6 px-4 sm:px-6 lg:px-8">

            {{-- 1. PROFIL + METRIK 30 HARI --}}
            <x-card>
                <div class="flex flex-col gap-6 lg:flex-row lg:items-start">
                    <div class="flex items-center gap-4 lg:w-72 lg:shrink-0">
                        @if ($user->avatar_path)
                            <img src="{{ asset($user->avatar_path) }}" alt="Foto profil {{ $user->name }}" class="h-16 w-16 shrink-0 rounded-full object-cover" />
                        @else
                            <span class="flex h-16 w-16 shrink-0 items-center justify-center rounded-full bg-telemetry-ink font-display text-xl font-bold text-white">
                                {{ $user->initials() }}
                            </span>
                        @endif

                        <div class="min-w-0">
                            <p class="telemetry-value truncate text-2xl">{{ $user->name }}</p>
                            <p class="truncate text-sm text-telemetry-slate">
                                {{ $user->username ? '@'.$user->username : $user->email }}
                            </p>
                            @if ($user->location)
                                <p class="mt-0.5 truncate text-[11px] text-telemetry-slate">📍 {{ $user->location }}</p>
                            @endif
                        </div>
                    </div>

                    <div class="min-w-0 flex-1 border-t border-telemetry-line pt-4 lg:border-l lg:border-t-0 lg:pl-6 lg:pt-0">
                        <p class="text-sm text-telemetry-ink">
                            {{ $user->bio ?: 'Belum ada bio. Lengkapi profil untuk konteks yang lebih kaya.' }}
                        </p>

                        <div class="mt-4">
                            <x-section-heading title="Metrik {{ $summaryDays }} Hari" hint="Berdasarkan Workout" />
                            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
                                <x-metric-tile
                                    label="Jarak"
                                    :value="$summary['distance_meters'] > 0 ? number_format($summary['distance_meters'] / 1000, 1) : null"
                                    unit="km"
                                />
                                <x-metric-tile
                                    label="Durasi"
                                    :value="$summary['duration_seconds'] > 0 ? $summaryDuration : null"
                                />
                                <x-metric-tile
                                    label="Aktivitas"
                                    :value="$summary['activity_count'] > 0 ? number_format($summary['activity_count']) : null"
                                />
                                <x-metric-tile
                                    label="Elevasi"
                                    :value="$summary['elevation_gain_meters'] > 0 ? number_format($summary['elevation_gain_meters']) : null"
                                    unit="m"
                                />
                                <x-metric-tile
                                    label="Relative Effort"
                                    :value="$summary['relative_effort'] > 0 ? number_format($summary['relative_effort']) : null"
                                />
                                <x-metric-tile label="Avg Pace" :value="$paceText" unit="/km" />
                            </div>
                        </div>
                    </div>
                </div>
            </x-card>

            {{-- 2. EXERTION GRID 364 HARI --}}
            <x-card>
                <x-section-heading title="Exertion Grid" hint="52 Minggu // Senin–Minggu" />

                <x-telemetry-exertion-grid
                    :weeks="$exertion['weeks']"
                    :metric-label="$exertion['metric_label']"
                    :active-days="$exertion['active_days']"
                    :total-score="$exertion['total_score']"
                    :start="$exertion['start']"
                    :end="$exertion['end']"
                />

                <p class="mt-3 text-[11px] text-telemetry-slate">
                    Intensitas harian memakai
                    <span class="font-bold text-telemetry-ink">{{ $exertion['metric_label'] }}</span>
                    @if ($exertion['metric'] === 'training_load')
                        — Relative Effort nol di seluruh periode, jadi grid jatuh ke training load dari Garmin.
                    @else
                        — jumlah skor Relative Effort harian yang dihitung RAGA dari zona HR kamu.
                    @endif
                    Puncak harian pada periode ini: {{ number_format($exertion['max'], 0) }}.
                </p>
            </x-card>

            {{-- 3. ATHLETIC DNA --}}
            <section class="grid gap-4 lg:grid-cols-2">
                <x-card>
                    <x-section-heading title="Discipline Ratio" :hint="$discipline['window_days'].' Hari'" />

                    @if ($discipline['total_count'] > 0)
                        <div class="space-y-3">
                            @foreach ($discipline['buckets'] as $bucket)
                                <div>
                                    <div class="flex items-baseline justify-between gap-3">
                                        <span class="text-sm font-bold text-telemetry-ink">{{ $bucket['label'] }}</span>
                                        <span class="telemetry-value text-sm">{{ $bucket['count_percent'] }}%</span>
                                    </div>
                                    <div class="mt-1.5 h-1.5 w-full bg-telemetry-well">
                                        <div class="h-full bg-telemetry-ink" style="width: {{ min(100, $bucket['count_percent']) }}%"></div>
                                    </div>
                                    <p class="mt-1 text-[11px] text-telemetry-slate">
                                        {{ $bucket['count'] }} aktivitas ·
                                        {{ number_format($bucket['distance_meters'] / 1000, 1) }} km
                                        ({{ $bucket['distance_percent'] }}% jarak)
                                    </p>
                                </div>
                            @endforeach
                        </div>

                        <p class="mt-4 border-t border-telemetry-line pt-3 text-[11px] text-telemetry-slate">
                            Bar menunjukkan proporsi jumlah aktivitas; persentase jarak ditulis di bawah tiap baris.
                            Total {{ number_format($discipline['total_distance_meters'] / 1000, 1) }} km dalam {{ $discipline['window_days'] }} hari.
                        </p>
                    @else
                        <p class="py-8 text-center text-sm text-telemetry-slate">
                            Belum ada aktivitas dalam {{ $discipline['window_days'] }} hari terakhir.
                        </p>
                    @endif
                </x-card>

                <x-card>
                    <x-section-heading title="Radar 5 Pilar Fisiologis" hint="Skor 0–100" />

                    <x-telemetry-radar-chart :pillars="$pillars" />

                    @unless ($hasAnyPillar)
                        <p class="mt-3 text-[11px] text-telemetry-slate">
                            Pilar ditampilkan "--" bila datanya belum ada. Tidak ada yang digambar sebagai nol.
                        </p>
                    @endunless
                </x-card>
            </section>

            {{-- 4. ANALITIK PERBANDINGAN --}}
            <x-card>
                <x-section-heading title="Perbandingan 28 Hari" hint="28 Hari Terakhir vs Sebelumnya" />

                <div class="grid gap-3 sm:grid-cols-3">
                    @foreach ($comparison as $cell)
                        @php
                            $tone = match ($cell['direction']) {
                                'up' => 'text-telemetry-emerald',
                                'down' => 'text-telemetry-ember-deep',
                                default => 'text-telemetry-slate',
                            };
                            $arrow = match ($cell['direction']) {
                                'up' => '▲',
                                'down' => '▼',
                                default => '■',
                            };
                        @endphp

                        <div class="border border-telemetry-line bg-telemetry-surface p-4">
                            <div class="flex items-center justify-between gap-2">
                                <p class="telemetry-label">{{ $cell['label'] }}</p>
                                <x-chip :variant="$cell['direction'] === 'up' ? 'recovery' : ($cell['direction'] === 'down' ? 'strain' : 'neutral')">
                                    {{ $cell['percent'] !== null ? ($cell['direction'] === 'flat' ? 'Stabil' : 'Berubah') : 'Tanpa Pembanding' }}
                                </x-chip>
                            </div>

                            <p class="mt-2.5 text-[28px] leading-none telemetry-value">
                                {{ $cell['current'] > 0 ? number_format($cell['current'], 1) : '0' }}@if ($cell['unit'])<span class="ml-1.5 text-[10px] font-semibold uppercase tracking-[0.08em] text-telemetry-slate">{{ $cell['unit'] }}</span>@endif
                            </p>

                            <p class="mt-2 text-[11px] {{ $tone }}">
                                @if ($cell['percent'] !== null)
                                    {{ $arrow }} {{ abs($cell['percent']) }}% vs {{ number_format($cell['previous'], 1) }}{{ $cell['unit'] ? ' '.$cell['unit'] : '' }}
                                @else
                                    -- belum ada periode pembanding
                                @endif
                            </p>
                        </div>
                    @endforeach
                </div>

                <p class="mt-3 text-[11px] text-telemetry-slate">
                    Pembanding nol ditulis "--" (tidak ada pembagian nol). Hijau = naik, merah = turun.
                </p>
            </x-card>

            {{-- 5. PERSONAL RECORDS SUITE --}}
            <section>
                <x-section-heading title="Personal Records Suite" :hint="$records !== [] ? 'Terbaik per Tipe' : null" />

                @if ($records !== [])
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ($records as $record)
                            <x-card class="!p-4">
                                <div class="flex items-start justify-between gap-2">
                                    <p class="telemetry-label">{{ $record['label'] }}</p>
                                    <x-chip variant="neutral">{{ $record['source'] }}</x-chip>
                                </div>
                                <p class="mt-2 telemetry-value text-3xl">
                                    {{ $record['value_text'] }}@if ($record['unit_text'])<span class="ml-1.5 text-sm font-semibold text-telemetry-slate">{{ $record['unit_text'] }}</span>@endif
                                </p>
                                <p class="mt-1 text-[11px] text-telemetry-slate">{{ $record['achieved_date'] }}</p>
                            </x-card>
                        @endforeach
                    </div>
                @else
                    <x-card class="py-10 text-center">
                        <p class="telemetry-value text-lg">Belum Ada Personal Record</p>
                        <p class="mt-2 text-sm text-telemetry-slate">
                            Personal record akan muncul setelah ada aktivitas (atau impor Garmin) yang tercatat.
                        </p>
                    </x-card>
                @endif
            </section>

        </div>
    </div>
</x-app-layout>
