<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="telemetry-value text-4xl sm:text-5xl">{{ __('Perbandingan Rute') }}</h1>
            <p class="mt-2 text-sm font-medium text-telemetry-slate">Performa kamu di rute trail yang sudah dilari berulang kali.</p>
        </div>
    </x-slot>

    <div class="py-6 pb-16">
        <div class="px-4 sm:px-6 lg:px-8 space-y-6">

            <div class="rounded border border-telemetry-line bg-telemetry-well px-4 py-3 text-xs font-medium text-telemetry-slate">
                ℹ️ {{ $disclaimer }} Perbandingan berdasarkan nama aktivitas yang sama, bukan pengukuran GPS presisi.
            </div>

            @forelse ($routeGroups as $group)
                <x-card>
                    <p class="mb-3 telemetry-value text-lg">{{ $group['name'] }}</p>

                    <x-route-comparison-map :routes="$mapRoutesByGroup[$group['name']]" />

                    <div class="mt-4 overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-telemetry-line text-left">
                                    <th class="py-2 pr-3 telemetry-label">Tanggal</th>
                                    <th class="py-2 pr-3 text-right telemetry-label">Jarak</th>
                                    <th class="py-2 pr-3 text-right telemetry-label">Pace</th>
                                    <th class="py-2 pr-3 text-right telemetry-label">Elevasi</th>
                                    <th class="py-2 pr-3 text-right telemetry-label">Selisih</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-telemetry-line">
                                @foreach ($group['runs'] as $run)
                                    @php
                                        $workout = $run['workout'];
                                        $p = $workout->average_pace_seconds_per_km ? (int) round($workout->average_pace_seconds_per_km) : null;
                                        $delta = $run['pace_delta_seconds'];
                                    @endphp
                                    <tr class="transition-colors hover:bg-telemetry-well">
                                        <td class="py-2 pr-3">
                                            <a href="{{ route('trail.show', $workout) }}" class="font-bold text-telemetry-ink transition-colors hover:text-telemetry-chrono">{{ $workout->start_date->translatedFormat('d M Y') }}</a>
                                            @if ($run['is_best'])
                                                <span class="ml-1 inline-flex h-5 items-center rounded border border-telemetry-emerald/20 bg-[rgba(0,184,101,0.08)] px-2 text-[10px] font-bold uppercase tracking-[0.08em] text-telemetry-emerald-deep">Terbaik</span>
                                            @endif
                                        </td>
                                        <td class="py-2 pr-3 text-right telemetry-value">{{ $workout->distance_meters ? number_format($workout->distance_meters / 1000, 2).' km' : '--' }}</td>
                                        <td class="py-2 pr-3 text-right telemetry-value">{{ $p ? sprintf('%d:%02d', intdiv($p, 60), $p % 60).'/km' : '--' }}</td>
                                        <td class="py-2 pr-3 text-right text-telemetry-slate">{{ $workout->elevation_gain_meters ? round($workout->elevation_gain_meters).' m' : '--' }}</td>
                                        <td class="py-2 pr-3 text-right">
                                            @if ($run['is_best'])
                                                <span class="text-telemetry-slate">—</span>
                                            @elseif ($delta !== null)
                                                @php $d = (int) round($delta); @endphp
                                                <span class="telemetry-value text-telemetry-ember-deep">+{{ sprintf('%d:%02d', intdiv(abs($d), 60), abs($d) % 60) }}/km</span>
                                            @else
                                                <span class="text-telemetry-slate/50">--</span>
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
                    <p class="text-sm text-telemetry-slate">Belum ada rute trail yang dilari lebih dari sekali (berdasarkan nama aktivitas yang sama).</p>
                </x-card>
            @endforelse

        </div>
    </div>
</x-app-layout>
