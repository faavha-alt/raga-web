<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="telemetry-value text-4xl sm:text-5xl">{{ __('Training Load') }}</h1>
            <p class="mt-2 text-sm font-medium text-telemetry-slate">Seberapa berat beban latihan kamu belakangan ini.</p>
        </div>
    </x-slot>

    <div class="py-6 pb-16">
        <div class="px-4 sm:px-6 lg:px-8 space-y-6">

            <div class="rounded border border-telemetry-line bg-telemetry-well px-4 py-3 text-xs font-medium text-telemetry-slate">
                ℹ️ {{ $disclaimer }} Acute:Chronic Ratio (ACWR) adalah indikator heuristik beban latihan, bukan diagnosis medis.
            </div>

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
                <div class="flex items-center justify-between gap-4">
                    <div class="flex items-center gap-2.5">
                        <span class="flex h-9 w-9 items-center justify-center border border-telemetry-line bg-telemetry-well text-base" aria-hidden="true">🎯</span>
                        <p class="telemetry-label-lg text-telemetry-ink">Training Status</p>
                    </div>
                    <span class="inline-flex h-5 items-center rounded border px-2 text-[10px] font-bold uppercase tracking-[0.08em] {{ $riskClasses[$status->risk_level] }}">{{ $riskLabels[$status->risk_level] }}</span>
                </div>
                <div class="mt-5 grid grid-cols-3 gap-4 border-t border-telemetry-line pt-4">
                    <div>
                        <p class="telemetry-label">Acute Load (7D)</p>
                        <p class="mt-1.5 telemetry-value text-2xl">{{ number_format($status->acute_load, 1) }}</p>
                    </div>
                    <div>
                        <p class="telemetry-label">Chronic Load (28D)</p>
                        <p class="mt-1.5 telemetry-value text-2xl">{{ number_format($status->chronic_load, 1) }}</p>
                    </div>
                    <div>
                        <p class="telemetry-label">Monotony</p>
                        <p class="mt-1.5 telemetry-value text-2xl">{{ $status->monotony !== null ? number_format($status->monotony, 2) : '--' }}</p>
                    </div>
                </div>
            </x-card>

            <x-card>
                <p class="mb-3 telemetry-label-lg text-telemetry-ink">Riwayat Training Load</p>
                <x-health-trend-chart :series="$trendSeries" :ranges="[7, 30, 90]" />
            </x-card>

            @if ($recentTrainingEffect->isNotEmpty())
                <div>
                    <p class="mb-3 telemetry-label-lg text-telemetry-ink">Training Effect Terakhir</p>
                    <x-card class="!p-0 divide-y divide-telemetry-line overflow-hidden">
                        @foreach ($recentTrainingEffect as $workout)
                            <div class="flex items-center justify-between px-5 py-3.5">
                                <div>
                                    <p class="text-sm font-bold text-telemetry-ink">{{ \App\Support\ActivityTypeIcon::label($workout->type) }}</p>
                                    <p class="text-[11px] text-telemetry-slate">{{ $workout->start_date->translatedFormat('d M Y') }} · {{ $workout->training_effect_label }}</p>
                                </div>
                                <div class="text-right text-[11px] text-telemetry-slate">
                                    <p>Aerobic <span class="telemetry-value">{{ $workout->training_effect_aerobic !== null ? number_format($workout->training_effect_aerobic, 1) : '--' }}</span></p>
                                    <p>Anaerobic <span class="telemetry-value">{{ $workout->training_effect_anaerobic !== null ? number_format($workout->training_effect_anaerobic, 1) : '--' }}</span></p>
                                </div>
                            </div>
                        @endforeach
                    </x-card>
                </div>
            @endif

        </div>
    </div>
</x-app-layout>
