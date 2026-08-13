<x-app-layout>
    <x-slot name="header">
        <h2 class="text-2xl font-extrabold text-gray-900 leading-tight">
            {{ __('Activities') }}
        </h2>
        <p class="mt-1 text-sm font-medium text-gray-500">Semua aktivitas yang tersinkron dari Garmin.</p>
    </x-slot>

    <div class="py-6 pb-16">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            {{-- Filter bar --}}
            <x-card class="!p-4">
                <form method="GET" action="{{ route('activities') }}" class="flex flex-wrap items-end gap-3">
                    <div class="flex-1 min-w-[160px]">
                        <x-input-label for="search" value="Cari (tipe aktivitas)" />
                        <x-text-input id="search" name="search" type="text" placeholder="mis. running" :value="$filters['search'] ?? ''" />
                    </div>

                    <div class="min-w-[140px]">
                        <x-input-label for="type" value="Tipe" />
                        <select id="type" name="type" class="w-full rounded-2xl border-2 border-gray-200 bg-gray-50 px-4 py-3 text-sm font-medium text-gray-900 focus:border-raga-primary focus:bg-white focus:ring-raga-primary transition">
                            <option value="">Semua</option>
                            @foreach ($types as $type)
                                <option value="{{ $type }}" @selected(($filters['type'] ?? '') === $type)>{{ ucwords(str_replace('_', ' ', $type)) }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="min-w-[140px]">
                        <x-input-label for="from" value="Dari Tanggal" />
                        <x-text-input id="from" name="from" type="date" :value="$filters['from'] ?? ''" />
                    </div>

                    <div class="min-w-[140px]">
                        <x-input-label for="to" value="Sampai Tanggal" />
                        <x-text-input id="to" name="to" type="date" :value="$filters['to'] ?? ''" />
                    </div>

                    <div class="min-w-[140px]">
                        <x-input-label for="sort" value="Urutkan" />
                        <select id="sort" name="sort" class="w-full rounded-2xl border-2 border-gray-200 bg-gray-50 px-4 py-3 text-sm font-medium text-gray-900 focus:border-raga-primary focus:bg-white focus:ring-raga-primary transition">
                            @foreach (['date' => 'Tanggal', 'distance' => 'Jarak', 'duration' => 'Durasi', 'calories' => 'Kalori', 'avg_hr' => 'Avg HR'] as $key => $optionLabel)
                                <option value="{{ $key }}" @selected(($filters['sort'] ?? 'date') === $key)>{{ $optionLabel }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="min-w-[120px]">
                        <x-input-label for="direction" value="Arah" />
                        <select id="direction" name="direction" class="w-full rounded-2xl border-2 border-gray-200 bg-gray-50 px-4 py-3 text-sm font-medium text-gray-900 focus:border-raga-primary focus:bg-white focus:ring-raga-primary transition">
                            <option value="desc" @selected(($filters['direction'] ?? 'desc') === 'desc')>Terbaru</option>
                            <option value="asc" @selected(($filters['direction'] ?? 'desc') === 'asc')>Terlama</option>
                        </select>
                    </div>

                    <div class="flex gap-2">
                        <x-primary-button type="submit">Terapkan</x-primary-button>
                        @if (array_filter($filters))
                            <a href="{{ route('activities') }}" class="inline-flex items-center rounded-full px-4 py-2.5 text-sm font-bold text-gray-400 hover:text-gray-600 transition">Reset</a>
                        @endif
                    </div>
                </form>
            </x-card>

            {{-- Summary --}}
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                <x-metric-tile icon="📋" label="Activities" :value="$summary['count']" />
                <x-metric-tile icon="📏" label="Distance" :value="number_format($summary['total_distance_meters'] / 1000, 1)" unit="km" />
                @php $sMin = intdiv($summary['total_duration_seconds'], 60); @endphp
                <x-metric-tile icon="⏱️" label="Duration" :value="intdiv($sMin, 60).'h '.($sMin % 60).'m'" />
                <x-metric-tile icon="🔥" label="Calories" :value="number_format($summary['total_calories'])" />
            </div>

            {{-- List --}}
            <x-card class="!p-0 divide-y divide-gray-100 overflow-hidden">
                @forelse ($activities as $workout)
                    @php
                        $icon = match (true) {
                            str_contains($workout->type, 'run') || $workout->type === 'walking' => '🏃',
                            str_contains($workout->type, 'bik') || str_contains($workout->type, 'cycl') => '🚴',
                            str_contains($workout->type, 'swim') => '🏊',
                            str_contains($workout->type, 'strength') => '🏋️',
                            default => '💪',
                        };
                        $durationMin = intdiv($workout->duration_seconds, 60);
                    @endphp
                    <a href="{{ route('activities.show', $workout) }}" class="flex items-center justify-between gap-4 px-5 py-4 hover:bg-gray-50 transition">
                        <div class="flex items-center gap-3 min-w-0">
                            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-gray-50 text-xl">{{ $icon }}</span>
                            <div class="min-w-0">
                                <p class="font-bold text-gray-900 truncate">{{ ucwords(str_replace('_', ' ', $workout->type)) }}</p>
                                <p class="text-xs text-gray-400">{{ $workout->start_date->translatedFormat('d M Y, H:i') }}</p>
                            </div>
                        </div>
                        <div class="text-right text-sm shrink-0">
                            <p class="font-bold text-gray-900">{{ $workout->distance_meters ? number_format($workout->distance_meters / 1000, 2).' km' : '--' }}</p>
                            <p class="text-xs text-gray-400">{{ intdiv($durationMin, 60) }}h {{ $durationMin % 60 }}m</p>
                        </div>
                    </a>
                @empty
                    <div class="px-5 py-10 text-center text-gray-400">Tidak ada aktivitas yang cocok dengan filter ini.</div>
                @endforelse
            </x-card>

            @if ($activities->hasPages())
                <div>{{ $activities->links() }}</div>
            @endif

        </div>
    </div>
</x-app-layout>
