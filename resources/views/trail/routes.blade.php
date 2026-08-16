<x-app-layout>
    <x-slot name="header">
        <h2 class="text-2xl font-extrabold text-gray-900 leading-tight">
            {{ __('Perbandingan Rute') }}
        </h2>
        <p class="mt-1 text-sm font-medium text-gray-500">Performa kamu di rute trail yang sudah dilari berulang kali.</p>
    </x-slot>

    <div class="py-6 pb-16">
        <div class="px-4 sm:px-6 lg:px-8 space-y-6">

            <div class="rounded-2xl bg-gray-50 border border-gray-100 px-4 py-3 text-xs font-semibold text-gray-500">
                ℹ️ {{ $disclaimer }} Perbandingan berdasarkan nama aktivitas yang sama, bukan pengukuran GPS presisi.
            </div>

            @forelse ($routeGroups as $group)
                <x-card>
                    <h3 class="mb-3 font-bold text-gray-900">{{ $group['name'] }}</h3>

                    <x-route-comparison-map :routes="$mapRoutesByGroup[$group['name']]" />

                    <div class="mt-4 overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="text-left text-[10px] font-bold uppercase text-gray-400 border-b border-gray-100">
                                    <th class="py-2 pr-3">Tanggal</th>
                                    <th class="py-2 pr-3">Jarak</th>
                                    <th class="py-2 pr-3">Pace</th>
                                    <th class="py-2 pr-3">Elevasi</th>
                                    <th class="py-2 pr-3">Selisih</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($group['runs'] as $run)
                                    @php
                                        $workout = $run['workout'];
                                        $p = $workout->average_pace_seconds_per_km ? (int) round($workout->average_pace_seconds_per_km) : null;
                                        $delta = $run['pace_delta_seconds'];
                                    @endphp
                                    <tr>
                                        <td class="py-2 pr-3">
                                            <a href="{{ route('trail.show', $workout) }}" class="font-bold text-gray-900 hover:text-raga-primary transition">{{ $workout->start_date->translatedFormat('d M Y') }}</a>
                                            @if ($run['is_best'])
                                                <span class="ml-1 text-[10px] font-bold text-raga-excellent">🏆 Terbaik</span>
                                            @endif
                                        </td>
                                        <td class="py-2 pr-3 text-gray-500">{{ $workout->distance_meters ? number_format($workout->distance_meters / 1000, 2).' km' : '--' }}</td>
                                        <td class="py-2 pr-3 text-gray-500">{{ $p ? sprintf('%d:%02d', intdiv($p, 60), $p % 60).'/km' : '--' }}</td>
                                        <td class="py-2 pr-3 text-gray-500">{{ $workout->elevation_gain_meters ? round($workout->elevation_gain_meters).' m' : '--' }}</td>
                                        <td class="py-2 pr-3">
                                            @if ($run['is_best'])
                                                <span class="text-gray-400">—</span>
                                            @elseif ($delta !== null)
                                                @php $d = (int) round($delta); @endphp
                                                <span class="font-bold text-raga-low">+{{ sprintf('%d:%02d', intdiv(abs($d), 60), abs($d) % 60) }}/km</span>
                                            @else
                                                <span class="text-gray-300">--</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </x-card>
            @empty
                <x-card class="text-center py-10">
                    <p class="text-gray-400">Belum ada rute trail yang dilari lebih dari sekali (berdasarkan nama aktivitas yang sama).</p>
                </x-card>
            @endforelse

        </div>
    </div>
</x-app-layout>
