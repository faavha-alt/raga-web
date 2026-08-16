<x-app-layout>
    <x-slot name="header">
        <h2 class="text-2xl font-extrabold text-gray-900 leading-tight">
            {{ __('Training Distribution') }}
        </h2>
        <p class="mt-1 text-sm font-medium text-gray-500">Distribusi tipe aktivitas & HR zone {{ $days }} hari terakhir.</p>
    </x-slot>

    <div class="py-6 pb-16">
        <div class="px-4 sm:px-6 lg:px-8 space-y-6">

            <div class="flex gap-2">
                @foreach ([7, 30, 90] as $rangeDays)
                    <a href="{{ route('training.distribution', ['days' => $rangeDays]) }}"
                       class="px-3 py-1.5 rounded-full text-xs font-bold transition {{ $days === $rangeDays ? 'bg-gray-900 text-white' : 'bg-gray-100 text-gray-500 hover:text-gray-800' }}">
                        {{ $rangeDays }}D
                    </a>
                @endforeach
            </div>

            <x-card>
                <h3 class="mb-4 text-xs font-bold uppercase tracking-wider text-gray-400">📈 Tipe Aktivitas</h3>
                <x-category-bar-chart :items="collect($types)->map(fn ($t) => [
                    'label' => $t['label'],
                    'icon' => $t['icon'],
                    'value' => $t['count'].'x',
                    'secondary' => number_format($t['distance_meters'] / 1000, 1).' km',
                    'percent' => $t['percent'],
                ])->all()" />
            </x-card>

            <x-card>
                <h3 class="mb-1 text-xs font-bold uppercase tracking-wider text-gray-400">❤️ Distribusi HR Zone</h3>

                @if ($hrZoneDistribution['available'])
                    <p class="mb-4 text-[11px] text-gray-400">
                        Estimasi HR maksimum: {{ $hrZoneDistribution['max_hr'] }} bpm — dari data tercatat kamu sendiri.
                        Dihitung dari {{ $hrZoneDistribution['workouts_with_samples'] }} dari {{ $hrZoneDistribution['workouts_total'] }} aktivitas yang punya data HR per-detik.
                    </p>
                    @php
                        $formatZoneDuration = fn ($seconds) => intdiv(intdiv($seconds, 60), 60).'h '.(intdiv($seconds, 60) % 60).'m';
                    @endphp
                    <x-category-bar-chart :items="collect($hrZoneDistribution['zones'])->map(fn ($z) => [
                        'label' => $z['label'],
                        'icon' => null,
                        'value' => $formatZoneDuration($z['seconds']),
                        'secondary' => null,
                        'percent' => $z['percent'],
                    ])->all()" />
                @else
                    <p class="mt-2 text-sm text-gray-400">
                        @if ($hrZoneDistribution['max_hr'] === null)
                            Belum ada data heart rate yang cukup untuk mengestimasi zona HR kamu.
                        @else
                            Belum ada aktivitas dengan data HR per-detik pada periode ini.
                        @endif
                    </p>
                @endif
            </x-card>

        </div>
    </div>
</x-app-layout>
