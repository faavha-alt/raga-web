<x-app-layout>
    <x-slot name="header">
        <h2 class="text-2xl font-extrabold text-gray-900 leading-tight">
            {{ __('Health Trends') }}
        </h2>
        <p class="mt-1 text-sm font-medium text-gray-500">Tren metrik kesehatan kamu dari waktu ke waktu.</p>
    </x-slot>

    <div class="py-6 pb-16">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            <div class="rounded-2xl bg-gray-50 border border-gray-100 px-4 py-3 text-xs font-semibold text-gray-500">
                ℹ️ {{ strtolower(\App\Services\Health\PersonalBaselineService::DISCLAIMER) }}
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                @foreach ($series as $key => $data)
                    @php
                        $latest = collect($data['points'])->last();
                        $b = $baselines[$key];
                    @endphp
                    <x-card class="!p-4">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400">{{ $data['label'] }}</p>
                        <p class="mt-1 text-xl font-black text-gray-900">
                            {{ $latest ? number_format($latest['value'], $data['decimals']) : '--' }}
                            <span class="text-xs font-semibold text-gray-400">{{ $data['unit'] }}</span>
                        </p>
                        @if ($b)
                            <p class="mt-1 text-[11px] font-semibold text-gray-400">{{ $b['percent_diff'] >= 0 ? '+' : '' }}{{ number_format($b['percent_diff'], 0) }}% vs baseline 30D</p>
                        @endif
                    </x-card>
                @endforeach
            </div>

            <x-card>
                <h3 class="mb-3 text-xs font-bold uppercase tracking-wider text-gray-400">Tren</h3>
                <x-health-trend-chart :series="$series" :ranges="[7, 30, 90, 182, 365]" />
            </x-card>

        </div>
    </div>
</x-app-layout>
