<x-app-layout>
    <x-slot name="header">
        <h2 class="text-2xl font-extrabold text-gray-900 leading-tight">
            {{ __('Health') }}
        </h2>
        <p class="mt-1 text-sm font-medium text-gray-500">Ringkasan kondisi tubuh kamu dari data Garmin.</p>
    </x-slot>

    <div class="py-6 pb-16">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            <div class="rounded-2xl bg-gray-50 border border-gray-100 px-4 py-3 text-xs font-semibold text-gray-500">
                ℹ️ Perbandingan "vs baseline" di halaman ini adalah {{ strtolower($disclaimer) }}
            </div>

            @php
                $sections = [
                    'Heart & HRV' => ['route' => 'health.heart', 'metrics' => ['resting_hr' => '❤️', 'avg_hr' => '💓', 'max_hr' => '🔺', 'hrv' => '📈']],
                    'Stress' => ['route' => 'health.stress', 'metrics' => ['stress' => '🧠']],
                    'Body Battery' => ['route' => 'health.body_battery', 'metrics' => ['body_battery_net' => '🔋']],
                    'Daily Metrics' => ['route' => 'health.daily_metrics', 'metrics' => ['respiration' => '🫁', 'spo2' => '🩸', 'steps' => '👣', 'calories' => '🔥']],
                ];
            @endphp

            @foreach ($sections as $title => $section)
                <div>
                    <div class="mb-3 flex items-center justify-between">
                        <h3 class="text-xs font-bold uppercase tracking-wider text-gray-400">{{ $title }}</h3>
                        <a href="{{ route($section['route']) }}" class="text-xs font-bold text-raga-primary hover:text-raga-accent transition">Lihat detail →</a>
                    </div>
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                        @foreach ($section['metrics'] as $key => $icon)
                            @php $data = $today[$key]; @endphp
                            <x-card class="!p-4">
                                <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400">{{ $icon }} {{ $data['meta']['label'] }}</p>
                                <p class="mt-1 text-2xl font-black text-gray-900">
                                    {{ $data['value'] !== null ? number_format($data['value'], $data['meta']['decimals']) : '--' }}
                                    @if ($data['value'] !== null && $data['meta']['unit'])
                                        <span class="text-sm font-semibold text-gray-400">{{ $data['meta']['unit'] }}</span>
                                    @endif
                                </p>
                                @if ($data['baseline'])
                                    @php $diff = $data['baseline']['percent_diff']; @endphp
                                    <p class="mt-1 text-[11px] font-semibold text-gray-400">
                                        {{ $diff >= 0 ? '+' : '' }}{{ number_format($diff, 0) }}% vs baseline 30D
                                    </p>
                                @endif
                            </x-card>
                        @endforeach
                    </div>
                </div>
            @endforeach

        </div>
    </div>
</x-app-layout>
