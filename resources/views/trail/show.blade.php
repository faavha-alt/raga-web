<x-app-layout>
    <x-slot name="header">
        <h2 class="text-2xl font-extrabold text-gray-900 leading-tight">
            {{ $workout->name ?? 'Trail Run' }}
        </h2>
        <p class="mt-1 text-sm font-medium text-gray-500">{{ $workout->start_date->translatedFormat('d M Y, H:i') }}</p>
    </x-slot>

    <div class="py-6 pb-16">
        <div class="px-4 sm:px-6 lg:px-8 space-y-6">

            @php $mMin = intdiv($movingSeconds, 60); @endphp
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                <x-metric-tile icon="📏" label="Distance" :value="$workout->distance_meters ? number_format($workout->distance_meters / 1000, 2) : '--'" unit="km" />
                <x-metric-tile icon="⛰️" label="Elevation Gain" :value="$workout->elevation_gain_meters ? round($workout->elevation_gain_meters) : '--'" unit="m" />
                <x-metric-tile icon="⏱️" label="Moving Time" :value="intdiv($mMin, 60).'h '.($mMin % 60).'m'" />
                <x-metric-tile icon="❤️" label="Avg HR" :value="$workout->average_heart_rate ? round($workout->average_heart_rate) : '--'" unit="bpm" />
            </div>

            @if ($profile['available'])
                <x-card>
                    <div class="flex items-center justify-between mb-1">
                        <h3 class="text-xs font-bold uppercase tracking-wider text-gray-400">Elevation & Grade Profile</h3>
                        <p class="text-xs text-gray-400">
                            Avg grade {{ $profile['avg_grade_percent'] }}% · Max grade {{ $profile['max_grade_percent'] }}%
                        </p>
                    </div>
                    <x-sample-chart label="Elevation" unit="m" color="#1baf7a" :points="$profile['points']" :decimals="0" />
                </x-card>
            @else
                <x-card class="text-center py-8">
                    <p class="text-gray-400">Belum cukup data GPS/elevasi untuk membuat profil trail ini.</p>
                </x-card>
            @endif

            @if (count($routePoints) > 1)
                <x-card>
                    <h3 class="mb-3 text-xs font-bold uppercase tracking-wider text-gray-400">Rute</h3>
                    <x-route-map :points="$routePoints" />
                </x-card>
            @endif

            <a href="{{ route('activities.show', $workout) }}" class="inline-block text-xs font-bold text-raga-primary hover:text-raga-accent transition">Lihat detail aktivitas lengkap →</a>

        </div>
    </div>
</x-app-layout>
