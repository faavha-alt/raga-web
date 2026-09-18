<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="telemetry-value text-2xl sm:text-3xl lg:text-5xl">{{ __('Running Distance') }}</h1>
            <p class="mt-1 text-sm font-medium text-telemetry-slate">Volume lari mingguan, bulanan, dan tahunan.</p>
        </div>
    </x-slot>

    <div class="py-4 sm:py-6 pb-16">
        <div class="px-4 sm:px-6 lg:px-8 space-y-3 sm:space-y-6">

            @php $p = $totals['average_pace_seconds_per_km'] ? (int) round($totals['average_pace_seconds_per_km']) : null; @endphp
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                <x-metric-tile icon="📏" label="Distance" :value="number_format($totals['distance_meters'] / 1000, 1)" unit="km" />
                @php $durMin = intdiv($totals['duration_seconds'], 60); @endphp
                <x-metric-tile icon="⏱️" label="Duration" :value="intdiv($durMin, 60).'h '.($durMin % 60).'m'" />
                <x-metric-tile icon="🏃" label="Runs" :value="$totals['activity_count']" />
                <x-metric-tile icon="⚡" label="Avg Pace" :value="$p ? sprintf('%d:%02d', intdiv($p, 60), $p % 60) : '--'" unit="/km" />
            </div>

            <x-card>
                <p class="mb-3 telemetry-label-lg text-telemetry-ink">Tren Jarak & Durasi</p>
                <x-health-trend-chart :series="$series" :ranges="[7, 30, 90, 365]" />
            </x-card>

        </div>
    </div>
</x-app-layout>
