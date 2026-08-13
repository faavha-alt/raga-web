<x-app-layout>
    <x-slot name="header">
        <h2 class="text-2xl font-extrabold text-gray-900 leading-tight">
            {{ __('Running Pace & HR') }}
        </h2>
        <p class="mt-1 text-sm font-medium text-gray-500">Tren pace, heart rate, dan VO2 Max dari lari kamu.</p>
    </x-slot>

    <div class="py-6 pb-16">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            <x-card>
                <h3 class="mb-3 text-xs font-bold uppercase tracking-wider text-gray-400">Tren</h3>
                <x-health-trend-chart :series="$series" :ranges="[7, 30, 90, 365]" />
            </x-card>

            <x-card class="!p-0 overflow-hidden">
                <h3 class="px-5 pt-5 pb-3 text-xs font-bold uppercase tracking-wider text-gray-400">Pace vs HR per Lari</h3>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-[10px] font-bold uppercase text-gray-400 border-b border-gray-100">
                                <th class="px-5 py-2">Tanggal</th>
                                <th class="px-5 py-2">Jarak</th>
                                <th class="px-5 py-2">Pace</th>
                                <th class="px-5 py-2">Avg HR</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($recentRuns as $run)
                                @php $p = $run->average_pace_seconds_per_km ? (int) round($run->average_pace_seconds_per_km) : null; @endphp
                                <tr>
                                    <td class="px-5 py-2.5 text-gray-500">{{ $run->start_date->translatedFormat('d M') }}</td>
                                    <td class="px-5 py-2.5 font-bold text-gray-900">{{ $run->distance_meters ? number_format($run->distance_meters / 1000, 2).' km' : '--' }}</td>
                                    <td class="px-5 py-2.5 font-bold text-gray-900">{{ $p ? sprintf('%d:%02d', intdiv($p, 60), $p % 60).'/km' : '--' }}</td>
                                    <td class="px-5 py-2.5 text-gray-500">{{ $run->average_heart_rate ? round($run->average_heart_rate).' bpm' : '--' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="px-5 py-6 text-center text-gray-400">Belum ada data pace.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="h-2"></div>
            </x-card>

        </div>
    </div>
</x-app-layout>
