<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="telemetry-value text-2xl sm:text-3xl lg:text-5xl">{{ __('Training Distribution') }}</h1>
            <p class="mt-1 text-sm font-medium text-telemetry-slate">Distribusi tipe aktivitas & HR zone {{ $days }} hari terakhir.</p>
        </div>
    </x-slot>

    <div class="py-4 sm:py-6 pb-16">
        <div class="px-4 sm:px-6 lg:px-8 space-y-3 sm:space-y-6">

            <div class="flex gap-2">
                @foreach ([7, 30, 90] as $rangeDays)
                    <a href="{{ route('training.distribution', ['days' => $rangeDays]) }}"
                       class="inline-flex items-center justify-center min-h-11 sm:min-h-0 rounded border px-3 py-1.5 font-display text-[11px] font-bold uppercase tracking-[0.08em] transition {{ $days === $rangeDays ? 'border-telemetry-ink bg-telemetry-ink text-white' : 'border-telemetry-line bg-telemetry-well text-telemetry-slate hover:text-telemetry-ink' }}">
                        {{ $rangeDays }}D
                    </a>
                @endforeach
            </div>

            <x-card>
                <p class="mb-4 telemetry-label-lg text-telemetry-ink">Tipe Aktivitas</p>
                <x-category-bar-chart :items="collect($types)->map(fn ($t) => [
                    'label' => $t['label'],
                    'icon' => $t['icon'],
                    'value' => $t['count'].'x',
                    'secondary' => number_format($t['distance_meters'] / 1000, 1).' km',
                    'percent' => $t['percent'],
                ])->all()" />
            </x-card>

            <x-card>
                <p class="mb-1 telemetry-label-lg text-telemetry-ink">Relative Effort</p>

                @if ($relativeEffortWorkouts > 0)
                    <p class="mt-2 telemetry-value text-3xl">{{ number_format($relativeEffortTotal) }}</p>
                    <p class="mt-1 text-[11px] text-telemetry-slate">
                        Total estimasi beban latihan dari {{ $relativeEffortWorkouts }} aktivitas yang punya data HR per-detik
                        (rata-rata {{ round($relativeEffortTotal / $relativeEffortWorkouts) }} per aktivitas).
                        Dihitung RAGA sendiri dari zona HR kamu (model TRIMP zona Edwards: bobot Z1–Z5 = 1–5),
                        bukan angka dari Garmin.
                    </p>
                @else
                    <p class="mt-2 text-sm text-telemetry-slate">Belum ada aktivitas dengan data HR per-detik pada periode ini.</p>
                @endif
            </x-card>

            <x-card>
                <p class="mb-1 telemetry-label-lg text-telemetry-ink">Distribusi HR Zone</p>

                @if ($hrZoneDistribution['available'])
                    <p class="mb-4 text-[11px] text-telemetry-slate">
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
                    <p class="mt-2 text-sm text-telemetry-slate">
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
