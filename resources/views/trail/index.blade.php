<x-app-layout>
    <x-slot name="header">
        <h2 class="text-2xl font-extrabold text-gray-900 leading-tight">
            {{ __('Trail') }}
        </h2>
        <p class="mt-1 text-sm font-medium text-gray-500">Ringkasan lari trail kamu dari data Garmin (90 hari terakhir).</p>
    </x-slot>

    <div class="py-6 pb-16">
        <div class="px-4 sm:px-6 lg:px-8 space-y-6">

            @php
                $mMin = intdiv($movingSeconds, 60);
                $p = $totals['average_pace_seconds_per_km'] ? (int) round($totals['average_pace_seconds_per_km']) : null;
            @endphp
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                <x-metric-tile icon="🏔️" label="Distance" :value="number_format($totals['distance_meters'] / 1000, 1)" unit="km" />
                <x-metric-tile icon="⛰️" label="Elevation Gain" :value="number_format($totals['elevation_gain_meters'])" unit="m" />
                <x-metric-tile icon="📉" label="Elevation Loss" :value="number_format($totals['elevation_loss_meters'])" unit="m" />
                <x-metric-tile icon="⏱️" label="Moving Time" :value="intdiv($mMin, 60).'h '.($mMin % 60).'m'" />
                <x-metric-tile icon="⚡" label="Avg Pace" :value="$p ? sprintf('%d:%02d', intdiv($p, 60), $p % 60) : '--'" unit="/km" />
                <x-metric-tile icon="❤️" label="Avg HR" :value="$totals['average_heart_rate'] ? round($totals['average_heart_rate']) : '--'" unit="bpm" />
            </div>

            <div class="flex flex-wrap gap-4">
                <a href="{{ route('activities', ['type' => 'trail_running']) }}" class="text-xs font-bold text-raga-primary hover:text-raga-accent transition">📋 Lihat semua trail run →</a>
                @if ($repeatedRouteCount > 0)
                    <a href="{{ route('trail.routes') }}" class="text-xs font-bold text-raga-primary hover:text-raga-accent transition">🔁 Bandingkan {{ $repeatedRouteCount }} rute berulang →</a>
                @endif
            </div>

            <div>
                <h3 class="mb-3 text-xs font-bold uppercase tracking-wider text-gray-400">🏔️ Trail Terakhir</h3>
                <x-card class="!p-0 divide-y divide-gray-100 overflow-hidden">
                    @forelse ($recentRuns as $run)
                        <a href="{{ route('trail.show', $run) }}" class="flex items-center justify-between px-5 py-3.5 hover:bg-gray-50 transition">
                            <div>
                                <p class="text-sm font-bold text-gray-900">{{ $run->name ?? 'Trail Run' }}</p>
                                <p class="text-xs text-gray-400">{{ $run->start_date->translatedFormat('d M Y') }}</p>
                            </div>
                            <div class="text-right text-sm">
                                <p class="font-bold text-gray-900">{{ $run->distance_meters ? number_format($run->distance_meters / 1000, 2).' km' : '--' }}</p>
                                <p class="text-xs text-gray-400">{{ $run->elevation_gain_meters ? round($run->elevation_gain_meters).' m gain' : '' }}</p>
                            </div>
                        </a>
                    @empty
                        <div class="px-5 py-6 text-center text-gray-400">Belum ada aktivitas trail running.</div>
                    @endforelse
                </x-card>
            </div>

        </div>
    </div>
</x-app-layout>
