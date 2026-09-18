<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="telemetry-value text-2xl sm:text-3xl lg:text-5xl">{{ __('Training') }}</h1>
            <p class="mt-1 text-sm font-medium text-telemetry-slate">Beban, konsistensi, dan distribusi latihanmu.</p>
        </div>
    </x-slot>

    <div class="py-4 sm:py-6 pb-16">
        <div class="px-4 sm:px-6 lg:px-8 space-y-3 sm:space-y-6">

            <div class="grid sm:grid-cols-2 gap-4">
                <x-card>
                    <div class="mb-4 flex items-end justify-between gap-4">
                        <p class="telemetry-label">Volume 7 Hari</p>
                        <a href="{{ route('training.volume') }}" class="inline-flex items-center min-h-11 sm:min-h-0 font-display text-[11px] font-bold uppercase tracking-[0.08em] text-telemetry-chrono hover:underline">Detail →</a>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <p class="telemetry-label">Jarak</p>
                            <p class="mt-1 telemetry-value text-xl">{{ number_format($weekTotals['distance_meters'] / 1000, 1) }}<span class="ml-1 text-[10px] font-semibold uppercase tracking-[0.08em] text-telemetry-slate">km</span></p>
                        </div>
                        <div>
                            @php $durMin = intdiv($weekTotals['duration_seconds'], 60); @endphp
                            <p class="telemetry-label">Durasi</p>
                            <p class="mt-1 telemetry-value text-xl">{{ intdiv($durMin, 60) }}h {{ $durMin % 60 }}m</p>
                        </div>
                        <div>
                            <p class="telemetry-label">Elevasi</p>
                            <p class="mt-1 telemetry-value text-xl">{{ number_format($weekTotals['elevation_gain_meters']) }}<span class="ml-1 text-[10px] font-semibold uppercase tracking-[0.08em] text-telemetry-slate">m</span></p>
                        </div>
                        <div>
                            <p class="telemetry-label">Aktivitas</p>
                            <p class="mt-1 telemetry-value text-xl">{{ $weekTotals['activity_count'] }}</p>
                        </div>
                    </div>
                </x-card>

                @php
                    $riskLabels = [
                        'undertraining' => 'Undertraining', 'optimal' => 'Optimal', 'caution' => 'Waspada',
                        'high_risk' => 'Risiko Tinggi', 'insufficient_data' => 'Data Belum Cukup',
                    ];
                    $riskClasses = [
                        'undertraining' => 'border-telemetry-chrono/20 bg-[rgba(0,112,243,0.08)] text-telemetry-chrono-deep',
                        'optimal' => 'border-telemetry-emerald/20 bg-[rgba(0,184,101,0.08)] text-telemetry-emerald-deep',
                        'caution' => 'border-[rgba(245,158,11,0.25)] bg-[rgba(245,158,11,0.10)] text-telemetry-amber',
                        'high_risk' => 'border-telemetry-ember/25 bg-[rgba(255,62,29,0.08)] text-telemetry-ember-deep',
                        'insufficient_data' => 'border-telemetry-line bg-telemetry-well text-telemetry-slate',
                    ];
                @endphp
                <x-card>
                    <div class="mb-4 flex items-end justify-between gap-4">
                        <p class="telemetry-label">Training Status</p>
                        <a href="{{ route('training.load') }}" class="inline-flex items-center min-h-11 sm:min-h-0 font-display text-[11px] font-bold uppercase tracking-[0.08em] text-telemetry-chrono hover:underline">Detail →</a>
                    </div>
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="telemetry-label">Acute:Chronic Ratio</p>
                            <p class="mt-1 telemetry-value text-2xl">{{ $status->acute_chronic_ratio !== null ? number_format($status->acute_chronic_ratio, 2) : '--' }}</p>
                        </div>
                        <span class="inline-flex h-5 items-center rounded border px-2 text-[10px] font-bold uppercase tracking-[0.08em] {{ $riskClasses[$status->risk_level] }}">{{ $riskLabels[$status->risk_level] }}</span>
                    </div>
                </x-card>

                <x-card>
                    <div class="mb-4 flex items-end justify-between gap-4">
                        <p class="telemetry-label">Konsistensi 30 Hari</p>
                        <a href="{{ route('training.calendar') }}" class="inline-flex items-center min-h-11 sm:min-h-0 font-display text-[11px] font-bold uppercase tracking-[0.08em] text-telemetry-chrono hover:underline">Detail →</a>
                    </div>
                    <div class="grid grid-cols-3 gap-4">
                        <div>
                            <p class="telemetry-label">Konsisten</p>
                            <p class="mt-1 telemetry-value text-xl">{{ $consistency['consistency_percent'] }}%</p>
                        </div>
                        <div>
                            <p class="telemetry-label">Rest Days</p>
                            <p class="mt-1 telemetry-value text-xl">{{ $consistency['rest_days'] }}</p>
                        </div>
                        <div>
                            <p class="telemetry-label">Streak Aktif</p>
                            <p class="mt-1 telemetry-value text-xl">{{ $consistency['current_streak_days'] }}</p>
                        </div>
                    </div>
                </x-card>

                <x-card>
                    <div class="mb-4 flex items-end justify-between gap-4">
                        <p class="telemetry-label">Distribusi Aktivitas</p>
                        <a href="{{ route('training.distribution') }}" class="inline-flex items-center min-h-11 sm:min-h-0 font-display text-[11px] font-bold uppercase tracking-[0.08em] text-telemetry-chrono hover:underline">Detail →</a>
                    </div>
                    @forelse ($topTypes as $type)
                        <div class="flex items-center justify-between border-b border-telemetry-line/70 py-1.5 text-sm last:border-0">
                            <span class="text-telemetry-slate">{{ $type['icon'] }} {{ $type['label'] }}</span>
                            <span class="telemetry-value">{{ $type['percent'] }}%</span>
                        </div>
                    @empty
                        <p class="text-sm text-telemetry-slate">Belum ada aktivitas 30 hari terakhir.</p>
                    @endforelse
                </x-card>
            </div>

            <div>
                <div class="mb-3 flex items-end justify-between gap-4">
                    <p class="telemetry-label-lg text-telemetry-ink">Training Plan</p>
                    <a href="{{ route('training.calendar') }}" class="inline-flex items-center min-h-11 sm:min-h-0 font-display text-[11px] font-bold uppercase tracking-[0.08em] text-telemetry-chrono hover:underline">Lihat Kalender →</a>
                </div>

                @if ($plans->isEmpty())
                    <x-card class="text-center py-10">
                        <p class="telemetry-value text-lg">Belum Ada Training Plan</p>
                        <p class="mt-2 text-sm text-telemetry-slate">Buat training plan untuk lihat jadwal mingguan kamu di sini.</p>
                    </x-card>
                @else
                    <div class="space-y-4">
                        @foreach ($plans as $plan)
                            <a href="{{ route('training.plan', $plan) }}">
                                <x-card class="hover:border-telemetry-line-strong">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <p class="font-bold text-telemetry-ink">{{ $plan->name }}</p>
                                            <p class="text-sm text-telemetry-slate">
                                                {{ ucfirst($plan->status) }} · {{ $plan->start_date->translatedFormat('d M') }} – {{ $plan->target_date->translatedFormat('d M Y') }}
                                            </p>
                                        </div>
                                        <span class="text-sm font-bold text-telemetry-chrono">→</span>
                                    </div>
                                </x-card>
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>

            @if ($personalRecords->isNotEmpty())
                <div>
                    <p class="mb-3 telemetry-label-lg text-telemetry-ink">Personal Records</p>
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                        @foreach ($personalRecords as $pr)
                            <x-card class="!p-4">
                                <p class="telemetry-label">{{ $pr->label() }}</p>
                                <p class="mt-1.5 telemetry-value text-xl">{{ $pr->formattedValue() }}</p>
                                <p class="mt-1 text-[11px] text-telemetry-slate">{{ $pr->achieved_date->translatedFormat('d M Y') }}</p>
                            </x-card>
                        @endforeach
                    </div>
                </div>
            @endif

            <div>
                <div class="mb-3 flex items-end justify-between gap-4">
                    <p class="telemetry-label-lg text-telemetry-ink">Aktivitas Terakhir</p>
                    <a href="{{ route('activities') }}" class="inline-flex items-center min-h-11 sm:min-h-0 font-display text-[11px] font-bold uppercase tracking-[0.08em] text-telemetry-chrono hover:underline">Lihat semua →</a>
                </div>
                <x-card class="!p-0 divide-y divide-telemetry-line overflow-hidden">
                    @forelse ($recentWorkouts as $workout)
                        @php
                            $icon = \App\Support\ActivityTypeIcon::icon($workout->type);
                            $pace = $workout->average_pace_seconds_per_km ? (int) round($workout->average_pace_seconds_per_km) : null;
                        @endphp
                        <a href="{{ route('activities.show', $workout) }}" class="flex items-center justify-between px-5 py-3.5 transition-colors hover:bg-telemetry-well">
                            <div class="flex items-center gap-3">
                                <span class="text-xl">{{ $icon }}</span>
                                <div>
                                    <p class="text-sm font-bold text-telemetry-ink">{{ \App\Support\ActivityTypeIcon::label($workout->type) }}</p>
                                    <p class="text-[11px] text-telemetry-slate">{{ $workout->start_date->translatedFormat('d M Y, H:i') }}</p>
                                </div>
                            </div>
                            <div class="text-right text-sm">
                                @if ($workout->distance_meters)
                                    <p class="telemetry-value">{{ number_format($workout->distance_meters / 1000, 2) }} km</p>
                                @endif
                                <p class="text-[11px] text-telemetry-slate">
                                    @if ($pace) {{ sprintf('%d:%02d', intdiv($pace, 60), $pace % 60) }}/km @endif
                                    @if ($workout->average_heart_rate) · {{ round($workout->average_heart_rate) }} bpm @endif
                                </p>
                            </div>
                        </a>
                    @empty
                        <div class="px-5 py-6 text-center text-sm text-telemetry-slate">Belum ada aktivitas</div>
                    @endforelse
                </x-card>
            </div>

        </div>
    </div>
</x-app-layout>
