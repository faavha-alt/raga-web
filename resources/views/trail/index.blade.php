<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="telemetry-value text-4xl sm:text-5xl">{{ __('Trail') }}</h1>
            <p class="mt-2 text-sm font-medium text-telemetry-slate">Ringkasan lari trail kamu dari data Garmin (90 hari terakhir).</p>
        </div>
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
                <a href="{{ route('activities', ['type' => 'trail_running']) }}" class="font-display text-[11px] font-bold uppercase tracking-[0.08em] text-telemetry-chrono hover:underline">📋 Lihat semua trail run →</a>
                @if ($repeatedRouteCount > 0)
                    <a href="{{ route('trail.routes') }}" class="font-display text-[11px] font-bold uppercase tracking-[0.08em] text-telemetry-chrono hover:underline">🔁 Bandingkan {{ $repeatedRouteCount }} rute berulang →</a>
                @endif
            </div>

            <div>
                <p class="mb-3 telemetry-label-lg text-telemetry-ink">Trail Terakhir</p>
                <x-card class="!p-0 divide-y divide-telemetry-line overflow-hidden">
                    @forelse ($recentRuns as $run)
                        <a href="{{ route('trail.show', $run) }}" class="flex items-center justify-between px-5 py-3.5 transition-colors hover:bg-telemetry-well">
                            <div>
                                <p class="text-sm font-bold text-telemetry-ink">{{ $run->name ?? 'Trail Run' }}</p>
                                <p class="mt-0.5 text-[11px] text-telemetry-slate">{{ $run->start_date->translatedFormat('d M Y') }}</p>
                            </div>
                            <div class="text-right text-sm">
                                <p class="telemetry-value">{{ $run->distance_meters ? number_format($run->distance_meters / 1000, 2).' km' : '--' }}</p>
                                <p class="mt-0.5 text-[11px] text-telemetry-slate">{{ $run->elevation_gain_meters ? round($run->elevation_gain_meters).' m gain' : '' }}</p>
                            </div>
                        </a>
                    @empty
                        <div class="px-5 py-6 text-center text-sm text-telemetry-slate">Belum ada aktivitas trail running.</div>
                    @endforelse
                </x-card>
            </div>

        </div>
    </div>
</x-app-layout>
