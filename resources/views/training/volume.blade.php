<x-app-layout>
    <x-slot name="header">
        <h2 class="text-2xl font-extrabold text-gray-900 leading-tight">
            {{ __('Training Volume') }}
        </h2>
        <p class="mt-1 text-sm font-medium text-gray-500">Volume latihan mingguan & bulanan dari data Garmin.</p>
    </x-slot>

    <div class="py-6 pb-16">
        <div class="px-4 sm:px-6 lg:px-8 space-y-6">

            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                <x-metric-tile icon="📏" label="Distance" :value="number_format($totals['distance_meters'] / 1000, 1)" unit="km" />
                @php $durMin = intdiv($totals['duration_seconds'], 60); @endphp
                <x-metric-tile icon="⏱️" label="Duration" :value="intdiv($durMin, 60).'h '.($durMin % 60).'m'" />
                <x-metric-tile icon="⛰️" label="Elevation" :value="number_format($totals['elevation_gain_meters'])" unit="m" />
                <x-metric-tile icon="🏃" label="Activities" :value="$totals['activity_count']" />
            </div>

            <x-card>
                <h3 class="mb-3 text-xs font-bold uppercase tracking-wider text-gray-400">Tren Volume</h3>
                <x-health-trend-chart :series="$series" :ranges="[7, 30, 90, 365]" />
            </x-card>

        </div>
    </div>
</x-app-layout>
