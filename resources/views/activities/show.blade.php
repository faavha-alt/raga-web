<x-app-layout>
    <x-slot name="header">
        <a href="{{ route('activities') }}" class="text-sm font-bold text-gray-400 hover:text-gray-600 transition">← Activities</a>
    </x-slot>

    <div class="py-6 pb-16">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            @php
                $icon = match (true) {
                    str_contains($workout->type, 'run') || $workout->type === 'walking' => '🏃',
                    str_contains($workout->type, 'bik') || str_contains($workout->type, 'cycl') => '🚴',
                    str_contains($workout->type, 'swim') => '🏊',
                    str_contains($workout->type, 'strength') => '🏋️',
                    default => '💪',
                };
                $durationMin = intdiv($workout->durationSeconds(), 60);
                $pace = $workout->average_pace_seconds_per_km ? (int) round($workout->average_pace_seconds_per_km) : null;
            @endphp

            <div class="flex items-center gap-4">
                <span class="flex h-14 w-14 items-center justify-center rounded-2xl bg-gray-50 text-2xl">{{ $icon }}</span>
                <div>
                    <h1 class="text-2xl font-black text-gray-900">{{ ucwords(str_replace('_', ' ', $workout->type)) }}</h1>
                    <p class="text-sm text-gray-500">{{ $workout->start_date->translatedFormat('l, d F Y — H:i') }}</p>
                </div>
            </div>

            <x-card>
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-5">
                    <div>
                        <p class="text-[10px] font-bold uppercase text-gray-400">Duration</p>
                        <p class="mt-0.5 text-lg font-black text-gray-900">{{ intdiv($durationMin, 60) }}h {{ $durationMin % 60 }}m</p>
                    </div>
                    @if ($workout->distance_meters)
                        <div>
                            <p class="text-[10px] font-bold uppercase text-gray-400">Distance</p>
                            <p class="mt-0.5 text-lg font-black text-gray-900">{{ number_format($workout->distance_meters / 1000, 2) }} km</p>
                        </div>
                    @endif
                    @if ($pace)
                        <div>
                            <p class="text-[10px] font-bold uppercase text-gray-400">Avg Pace</p>
                            <p class="mt-0.5 text-lg font-black text-gray-900">{{ sprintf('%d:%02d', intdiv($pace, 60), $pace % 60) }} /km</p>
                        </div>
                    @endif
                    @if ($workout->average_heart_rate)
                        <div>
                            <p class="text-[10px] font-bold uppercase text-gray-400">Avg HR</p>
                            <p class="mt-0.5 text-lg font-black text-gray-900">{{ round($workout->average_heart_rate) }} bpm</p>
                        </div>
                    @endif
                    @if ($workout->max_heart_rate)
                        <div>
                            <p class="text-[10px] font-bold uppercase text-gray-400">Max HR</p>
                            <p class="mt-0.5 text-lg font-black text-gray-900">{{ round($workout->max_heart_rate) }} bpm</p>
                        </div>
                    @endif
                    @if ($workout->active_calories)
                        <div>
                            <p class="text-[10px] font-bold uppercase text-gray-400">Calories</p>
                            <p class="mt-0.5 text-lg font-black text-gray-900">{{ round($workout->active_calories) }}</p>
                        </div>
                    @endif
                    @if ($workout->elevation_gain_meters)
                        <div>
                            <p class="text-[10px] font-bold uppercase text-gray-400">Elevation Gain</p>
                            <p class="mt-0.5 text-lg font-black text-gray-900">{{ round($workout->elevation_gain_meters) }} m</p>
                        </div>
                    @endif
                </div>
            </x-card>

            @if (collect($charts)->every(fn ($c) => empty($c['points'])))
                <x-card class="text-center py-8">
                    <p class="text-gray-400">Tidak ada data time-series (heart rate/pace/elevation) untuk aktivitas ini.</p>
                </x-card>
            @else
                @foreach ($charts as $chart)
                    @if (! empty($chart['points']))
                        <x-card>
                            <x-sample-chart
                                :label="$chart['label']"
                                :unit="$chart['unit']"
                                :color="$chart['color']"
                                :points="$chart['points']"
                                :decimals="$chart['decimals']"
                            />
                        </x-card>
                    @endif
                @endforeach
            @endif

        </div>
    </div>
</x-app-layout>
