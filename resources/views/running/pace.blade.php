<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="telemetry-value text-2xl sm:text-3xl lg:text-5xl">{{ __('Running Pace & HR') }}</h1>
            <p class="mt-1 text-sm font-medium text-telemetry-slate">Tren pace, heart rate, dan VO2 Max dari lari kamu.</p>
        </div>
    </x-slot>

    <div class="py-4 sm:py-6 pb-16">
        <div class="px-4 sm:px-6 lg:px-8 space-y-3 sm:space-y-6">

            <x-card>
                <p class="mb-3 telemetry-label-lg text-telemetry-ink">Tren</p>
                <x-health-trend-chart :series="$series" :ranges="[7, 30, 90, 365]" />
            </x-card>

            <x-card class="!p-0 overflow-hidden">
                <p class="px-5 pt-5 pb-3 telemetry-label-lg text-telemetry-ink">Pace vs HR per Lari</p>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-telemetry-line text-left">
                                <th class="px-5 py-2 telemetry-label">Tanggal</th>
                                <th class="px-5 py-2 text-right telemetry-label">Jarak</th>
                                <th class="px-5 py-2 text-right telemetry-label">Pace</th>
                                <th class="px-5 py-2 text-right telemetry-label">Avg HR</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-telemetry-line">
                            @forelse ($recentRuns as $run)
                                @php $p = $run->average_pace_seconds_per_km ? (int) round($run->average_pace_seconds_per_km) : null; @endphp
                                <tr class="transition-colors hover:bg-telemetry-well">
                                    <td class="px-5 py-2.5 text-telemetry-slate">{{ $run->start_date->translatedFormat('d M') }}</td>
                                    <td class="px-5 py-2.5 text-right telemetry-value">{{ $run->distance_meters ? number_format($run->distance_meters / 1000, 2).' km' : '--' }}</td>
                                    <td class="px-5 py-2.5 text-right telemetry-value">{{ $p ? sprintf('%d:%02d', intdiv($p, 60), $p % 60).'/km' : '--' }}</td>
                                    <td class="px-5 py-2.5 text-right text-telemetry-slate">{{ $run->average_heart_rate ? round($run->average_heart_rate).' bpm' : '--' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="px-5 py-6 text-center text-sm text-telemetry-slate">Belum ada data pace.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="h-2"></div>
            </x-card>

        </div>
    </div>
</x-app-layout>
