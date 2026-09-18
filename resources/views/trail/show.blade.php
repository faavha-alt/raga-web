<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="telemetry-value text-2xl sm:text-3xl lg:text-5xl">{{ $workout->name ?? 'Trail Run' }}</h1>
            <p class="mt-1 text-sm font-medium text-telemetry-slate">{{ $workout->start_date->translatedFormat('d M Y, H:i') }}</p>
        </div>
    </x-slot>

    <div class="py-4 sm:py-6 pb-16">
        <div class="px-4 sm:px-6 lg:px-8 space-y-3 sm:space-y-6">

            @php $mMin = intdiv($movingSeconds, 60); @endphp
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                <x-metric-tile icon="📏" label="Distance" :value="$workout->distance_meters ? number_format($workout->distance_meters / 1000, 2) : '--'" unit="km" />
                <x-metric-tile icon="⛰️" label="Elevation Gain" :value="$workout->elevation_gain_meters ? round($workout->elevation_gain_meters) : '--'" unit="m" />
                <x-metric-tile icon="⏱️" label="Moving Time" :value="intdiv($mMin, 60).'h '.($mMin % 60).'m'" />
                <x-metric-tile icon="❤️" label="Avg HR" :value="$workout->average_heart_rate ? round($workout->average_heart_rate) : '--'" unit="bpm" />
            </div>

            @if ($profile['available'])
                <x-card>
                    <div class="mb-1 flex items-end justify-between gap-4">
                        <p class="telemetry-label-lg text-telemetry-ink">Elevation & Grade Profile</p>
                        <p class="telemetry-label">
                            Avg grade {{ $profile['avg_grade_percent'] }}% · Max grade {{ $profile['max_grade_percent'] }}%
                        </p>
                    </div>
                    <x-sample-chart label="Elevation" unit="m" color="#1baf7a" :points="$profile['points']" :decimals="0" />
                </x-card>
            @else
                <x-card class="text-center py-8">
                    <p class="text-sm text-telemetry-slate">Belum cukup data GPS/elevasi untuk membuat profil trail ini.</p>
                </x-card>
            @endif

            @if (count($routePoints) > 1)
                <x-card>
                    <p class="mb-3 telemetry-label-lg text-telemetry-ink">Rute</p>
                    <x-route-map :points="$routePoints" />
                </x-card>
            @endif

            <a href="{{ route('activities.show', $workout) }}" class="inline-flex items-center min-h-11 sm:min-h-0 font-display text-[11px] font-bold uppercase tracking-[0.08em] text-telemetry-chrono hover:underline">Lihat detail aktivitas lengkap →</a>

        </div>
    </div>
</x-app-layout>
