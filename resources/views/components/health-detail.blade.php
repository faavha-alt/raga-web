@props(['series', 'baselines', 'metrics', 'dailyRows', 'disclaimer', 'ranges' => [7, 30, 90, 365]])

<div class="px-4 sm:px-6 lg:px-8 space-y-6">

    <x-card>
        <x-health-trend-chart :series="$series" :ranges="$ranges" />
    </x-card>

    @php $anyBaseline = collect($baselines)->filter()->isNotEmpty(); @endphp
    @if ($anyBaseline)
        <div>
            <div class="mb-3 rounded-2xl bg-gray-50 border border-gray-100 px-4 py-3 text-xs font-semibold text-gray-500">
                ℹ️ {{ $disclaimer }}
            </div>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                @foreach ($metrics as $metric)
                    @php $b = $baselines[$metric] ?? null; $meta = $series[$metric]; @endphp
                    @if ($b)
                        <x-card class="!p-4">
                            <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400">{{ $meta['label'] }} baseline</p>
                            <p class="mt-1 text-lg font-black text-gray-900">
                                {{ number_format($b['mean'], $meta['decimals']) }}
                                <span class="text-xs font-semibold text-gray-400">{{ $meta['unit'] }}</span>
                            </p>
                            <p class="mt-0.5 text-[11px] text-gray-400">
                                {{ $b['sample_count'] }} data poin · {{ $b['percent_diff'] >= 0 ? '+' : '' }}{{ number_format($b['percent_diff'], 0) }}% dari nilai terakhir
                            </p>
                        </x-card>
                    @endif
                @endforeach
            </div>
        </div>
    @endif

    <div>
        <h3 class="mb-3 text-xs font-bold uppercase tracking-wider text-gray-400">Ringkasan Harian</h3>
        <x-card class="!p-0 divide-y divide-gray-100 overflow-hidden">
            @forelse ($dailyRows as $row)
                <div class="flex items-center justify-between px-5 py-3 gap-4">
                    <span class="text-sm font-semibold text-gray-600 shrink-0">{{ \Illuminate\Support\Carbon::parse($row['date'])->translatedFormat('D, d M') }}</span>
                    <div class="flex flex-wrap justify-end gap-x-4 gap-y-1">
                        @foreach ($metrics as $metric)
                            <span class="text-sm font-bold text-gray-900 whitespace-nowrap">
                                {{ isset($row['values'][$metric]) ? number_format($row['values'][$metric], $series[$metric]['decimals']) : '--' }}
                                <span class="text-[10px] font-normal text-gray-400">{{ $series[$metric]['unit'] }}</span>
                            </span>
                        @endforeach
                    </div>
                </div>
            @empty
                <div class="px-5 py-6 text-center text-gray-400">Belum ada data</div>
            @endforelse
        </x-card>
    </div>

</div>
