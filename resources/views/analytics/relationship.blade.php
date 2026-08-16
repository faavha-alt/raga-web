<x-app-layout>
    <x-slot name="header">
        <h2 class="text-2xl font-extrabold text-gray-900 leading-tight">
            {{ $definition['title'] }}
        </h2>
        <p class="mt-1 text-sm font-medium text-gray-500">Apakah dua data ini bergerak bersama dari waktu ke waktu.</p>
    </x-slot>

    <div class="py-6 pb-16">
        <div class="px-4 sm:px-6 lg:px-8 space-y-6">

            <div class="rounded-2xl bg-gray-50 border border-gray-100 px-4 py-3 text-xs font-semibold text-gray-500">
                ℹ️ {{ $disclaimer }}
            </div>

            <div class="flex gap-2">
                @foreach ([7 => '7D', 30 => '30D', 90 => '90D', 182 => '6M', 365 => '1Y'] as $rangeDays => $label)
                    <a href="{{ route('analytics.relationship', ['pair' => $definition['slug'], 'days' => $rangeDays]) }}"
                       class="px-3 py-1.5 rounded-full text-xs font-bold transition {{ $days === $rangeDays ? 'bg-gray-900 text-white' : 'bg-gray-100 text-gray-500 hover:text-gray-800' }}">
                        {{ $label }}
                    </a>
                @endforeach
            </div>

            @php
                $strengthClasses = match ($result['strength']) {
                    'strong' => 'bg-emerald-50 text-emerald-600',
                    'moderate' => 'bg-sky-50 text-sky-600',
                    'weak' => 'bg-amber-50 text-amber-600',
                    default => 'bg-gray-100 text-gray-400',
                };
            @endphp
            <x-card>
                <div class="flex items-center justify-between mb-2 gap-3">
                    <p class="text-sm font-bold text-gray-500">Hasil</p>
                    <span class="shrink-0 rounded-full px-3 py-1 text-xs font-bold {{ $strengthClasses }}">
                        @if ($result['sufficient_data'])
                            r = {{ $result['r'] !== null ? number_format($result['r'], 2) : '--' }} · {{ $result['paired_count'] }} hari
                        @else
                            {{ $result['paired_count'] }} hari data
                        @endif
                    </span>
                </div>
                <p class="text-gray-900 font-semibold">{{ $description }}</p>
            </x-card>

            <x-card>
                <h3 class="mb-3 text-xs font-bold uppercase tracking-wider text-gray-400">Perbandingan</h3>
                <x-health-trend-chart :series="$series" :ranges="[$days]" />
            </x-card>

        </div>
    </div>
</x-app-layout>
