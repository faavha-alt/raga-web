<x-app-layout>
    <x-slot name="header">
        <h2 class="text-2xl font-extrabold text-gray-900 leading-tight">
            {{ __('Training Load') }}
        </h2>
        <p class="mt-1 text-sm font-medium text-gray-500">Seberapa berat beban latihan kamu belakangan ini.</p>
    </x-slot>

    <div class="py-6 pb-16">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            <div class="rounded-2xl bg-gray-50 border border-gray-100 px-4 py-3 text-xs font-semibold text-gray-500">
                ℹ️ {{ $disclaimer }} Acute:Chronic Ratio (ACWR) adalah indikator heuristik beban latihan, bukan diagnosis medis.
            </div>

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
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2.5">
                        <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-raga-accent to-raga-primary text-white text-base shadow-glow">🎯</span>
                        <p class="text-sm font-bold text-gray-500">Training Status</p>
                    </div>
                    <span class="rounded-full px-3 py-1 text-xs font-bold {{ $riskClasses[$status->risk_level] }}">{{ $riskLabels[$status->risk_level] }}</span>
                </div>
                <div class="mt-4 grid grid-cols-3 gap-3">
                    <div>
                        <p class="text-2xl font-black text-gray-900">{{ number_format($status->acute_load, 1) }}</p>
                        <p class="text-[11px] text-gray-400">Acute Load (7D)</p>
                    </div>
                    <div>
                        <p class="text-2xl font-black text-gray-900">{{ number_format($status->chronic_load, 1) }}</p>
                        <p class="text-[11px] text-gray-400">Chronic Load (28D)</p>
                    </div>
                    <div>
                        <p class="text-2xl font-black text-gray-900">{{ $status->monotony !== null ? number_format($status->monotony, 2) : '--' }}</p>
                        <p class="text-[11px] text-gray-400">Monotony</p>
                    </div>
                </div>
            </x-card>

            <x-card>
                <h3 class="mb-3 text-xs font-bold uppercase tracking-wider text-gray-400">Riwayat Training Load</h3>
                <x-health-trend-chart :series="$trendSeries" :ranges="[7, 30, 90]" />
            </x-card>

            @if ($recentTrainingEffect->isNotEmpty())
                <div>
                    <h3 class="mb-3 text-xs font-bold uppercase tracking-wider text-gray-400">💪 Training Effect Terakhir</h3>
                    <x-card class="!p-0 divide-y divide-gray-100 overflow-hidden">
                        @foreach ($recentTrainingEffect as $workout)
                            <div class="flex items-center justify-between px-5 py-3.5">
                                <div>
                                    <p class="text-sm font-bold text-gray-900">{{ \App\Support\ActivityTypeIcon::label($workout->type) }}</p>
                                    <p class="text-xs text-gray-400">{{ $workout->start_date->translatedFormat('d M Y') }} · {{ $workout->training_effect_label }}</p>
                                </div>
                                <div class="text-right text-xs text-gray-400">
                                    <p>Aerobic {{ $workout->training_effect_aerobic !== null ? number_format($workout->training_effect_aerobic, 1) : '--' }}</p>
                                    <p>Anaerobic {{ $workout->training_effect_anaerobic !== null ? number_format($workout->training_effect_anaerobic, 1) : '--' }}</p>
                                </div>
                            </div>
                        @endforeach
                    </x-card>
                </div>
            @endif

        </div>
    </div>
</x-app-layout>
