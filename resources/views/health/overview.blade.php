<x-app-layout>
    <x-slot name="header">
        <h1 class="telemetry-value text-4xl sm:text-5xl">
            {{ __('Health') }}
        </h1>
        <p class="mt-2 text-sm font-medium text-telemetry-slate">Ringkasan kondisi tubuh kamu dari data Garmin.</p>
    </x-slot>

    <div class="py-6 pb-16">
        <div class="px-4 sm:px-6 lg:px-8 space-y-6">

            <div class="rounded border border-telemetry-line bg-telemetry-well px-4 py-3 text-xs font-semibold text-telemetry-slate">
                ℹ️ Perbandingan "vs baseline" di halaman ini adalah {{ strtolower($disclaimer) }}
            </div>

            @php
                $sections = [
                    'Heart Rate' => ['route' => 'health.heart', 'metrics' => ['resting_hr' => '❤️', 'avg_hr' => '💓', 'max_hr' => '🔺']],
                    'Stress' => ['route' => 'health.stress', 'metrics' => ['stress' => '🧠']],
                    'Body Battery' => ['route' => 'health.body_battery', 'metrics' => ['body_battery_net' => '🔋']],
                    'Daily Metrics' => ['route' => 'health.daily_metrics', 'metrics' => ['respiration' => '🫁', 'spo2' => '🩸', 'steps' => '👣', 'calories' => '🔥', 'recovery_time' => '🔴']],
                ];
            @endphp

            @foreach ($sections as $title => $section)
                <div>
                    <div class="mb-3 flex items-end justify-between gap-4">
                        <h3 class="telemetry-label-lg text-telemetry-ink">{{ $title }}</h3>
                        <a href="{{ route($section['route']) }}" class="telemetry-label transition-colors hover:text-telemetry-ink">Lihat detail →</a>
                    </div>
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                        @foreach ($section['metrics'] as $key => $icon)
                            @php $data = $today[$key]; @endphp
                            <x-card class="!p-4">
                                <p class="telemetry-label">{{ $icon }} {{ $data['meta']['label'] }}</p>
                                <p class="mt-2 telemetry-value text-2xl">
                                    {{ $data['value'] !== null ? number_format($data['value'], $data['meta']['decimals']) : '--' }}
                                    @if ($data['value'] !== null && $data['meta']['unit'])
                                        <span class="text-sm font-semibold text-telemetry-slate">{{ $data['meta']['unit'] }}</span>
                                    @endif
                                </p>
                                @if ($data['baseline'])
                                    @php $diff = $data['baseline']['percent_diff']; @endphp
                                    <p class="mt-1 text-[11px] font-semibold text-telemetry-slate">
                                        {{ $diff >= 0 ? '+' : '' }}{{ number_format($diff, 0) }}% vs baseline 30D
                                    </p>
                                @endif
                            </x-card>
                        @endforeach
                    </div>
                </div>
            @endforeach

            @php $garminReadiness = $today['garmin_readiness']; @endphp
            <div>
                <h3 class="mb-3 telemetry-label-lg text-telemetry-ink">Untuk Perbandingan</h3>
                <x-card class="!p-4">
                    <p class="telemetry-label">🔵 {{ $garminReadiness['meta']['label'] }}</p>
                    <p class="mt-2 telemetry-value text-2xl">
                        {{ $garminReadiness['value'] !== null ? round($garminReadiness['value']) : '--' }}
                    </p>
                    <p class="mt-2 text-[11px] text-telemetry-slate">Skor readiness bawaan Garmin (perhitungannya tertutup) — beda dengan <a href="{{ route('recovery') }}" class="font-semibold text-telemetry-chrono hover:text-telemetry-ember transition-colors">Readiness Score</a> kita yang transparan.</p>
                </x-card>
            </div>

        </div>
    </div>
</x-app-layout>
