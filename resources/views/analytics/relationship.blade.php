<x-app-layout>
    <x-slot name="header">
        <h1 class="telemetry-value text-2xl sm:text-3xl lg:text-5xl">
            {{ $definition['title'] }}
        </h1>
        <p class="mt-1 text-sm font-medium text-telemetry-slate">Apakah dua data ini bergerak bersama dari waktu ke waktu.</p>
    </x-slot>

    <div class="py-4 sm:py-6 pb-16">
        <div class="px-4 sm:px-6 lg:px-8 space-y-3 sm:space-y-6">

            <div class="rounded border border-telemetry-line bg-telemetry-well px-4 py-3 text-xs font-semibold text-telemetry-slate">
                ℹ️ {{ $disclaimer }}
            </div>

            <div class="flex flex-wrap gap-2">
                @foreach ([7 => '7D', 30 => '30D', 90 => '90D', 182 => '6M', 365 => '1Y'] as $rangeDays => $label)
                    <a href="{{ route('analytics.relationship', ['pair' => $definition['slug'], 'days' => $rangeDays]) }}"
                       class="inline-flex items-center justify-center min-h-11 sm:min-h-0 px-3 py-1.5 rounded border text-[10px] font-bold uppercase tracking-[0.08em] transition-colors {{ $days === $rangeDays ? 'bg-telemetry-ink text-white border-telemetry-ink' : 'bg-telemetry-well text-telemetry-slate border-telemetry-line hover:text-telemetry-ink hover:border-telemetry-line-strong' }}">
                        {{ $label }}
                    </a>
                @endforeach
            </div>

            @php
                $strengthClasses = match ($result['strength']) {
                    'strong' => 'bg-[rgba(0,184,101,0.08)] text-telemetry-emerald-deep border-telemetry-emerald/20',
                    'moderate' => 'bg-[rgba(0,112,243,0.08)] text-telemetry-chrono-deep border-telemetry-chrono/20',
                    'weak' => 'bg-[rgba(245,158,11,0.08)] text-telemetry-amber border-telemetry-amber/20',
                    default => 'bg-telemetry-well text-telemetry-slate border-telemetry-line',
                };
            @endphp
            <x-card>
                <div class="flex items-center justify-between mb-2 gap-3">
                    <p class="text-sm font-bold text-telemetry-slate">Hasil</p>
                    <span class="shrink-0 rounded border px-2 py-1 text-[10px] font-bold uppercase tracking-[0.08em] {{ $strengthClasses }}">
                        @if ($result['sufficient_data'])
                            r = {{ $result['r'] !== null ? number_format($result['r'], 2) : '--' }} · {{ $result['paired_count'] }} hari
                        @else
                            {{ $result['paired_count'] }} hari data
                        @endif
                    </span>
                </div>
                <p class="text-telemetry-ink font-semibold">{{ $description }}</p>
            </x-card>

            <x-card>
                <h3 class="mb-3 telemetry-label-lg text-telemetry-ink">Perbandingan</h3>
                <x-health-trend-chart :series="$series" :ranges="[$days]" />
            </x-card>

        </div>
    </div>
</x-app-layout>
