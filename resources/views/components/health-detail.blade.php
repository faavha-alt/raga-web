@props(['series', 'baselines', 'metrics', 'dailyRows', 'disclaimer', 'ranges' => [7, 30, 90, 365]])

<div class="space-y-6 px-4 sm:px-6 lg:px-8">

    <x-card>
        <x-health-trend-chart :series="$series" :ranges="$ranges" />
    </x-card>

    @php $anyBaseline = collect($baselines)->filter()->isNotEmpty(); @endphp
    @if ($anyBaseline)
        <div>
            <div class="mb-3 rounded border border-telemetry-line bg-telemetry-well px-4 py-3 text-xs font-semibold text-telemetry-slate">
                ℹ️ {{ $disclaimer }}
            </div>
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                @foreach ($metrics as $metric)
                    @php $b = $baselines[$metric] ?? null; $meta = $series[$metric]; @endphp
                    @if ($b)
                        <x-card class="!p-4">
                            <p class="telemetry-label">{{ $meta['label'] }} baseline</p>
                            <p class="mt-1.5 text-lg telemetry-value">
                                {{ number_format($b['mean'], $meta['decimals']) }}
                                <span class="text-[10px] font-semibold uppercase tracking-[0.08em] text-telemetry-slate">{{ $meta['unit'] }}</span>
                            </p>
                            <p class="mt-1 text-[11px] text-telemetry-slate">
                                {{ $b['sample_count'] }} data poin · {{ $b['percent_diff'] >= 0 ? '+' : '' }}{{ number_format($b['percent_diff'], 0) }}% dari nilai terakhir
                            </p>
                        </x-card>
                    @endif
                @endforeach
            </div>
        </div>
    @endif

    <div>
        <x-section-heading title="Ringkasan Harian" />
        <x-card class="!p-0 divide-y divide-telemetry-line overflow-hidden">
            @forelse ($dailyRows as $row)
                <div class="flex items-center justify-between gap-4 px-5 py-3 transition-colors hover:bg-telemetry-well">
                    <span class="shrink-0 text-sm font-semibold text-telemetry-slate">{{ \Illuminate\Support\Carbon::parse($row['date'])->translatedFormat('D, d M') }}</span>
                    <div class="flex flex-wrap justify-end gap-x-4 gap-y-1">
                        @foreach ($metrics as $metric)
                            <span class="telemetry-value whitespace-nowrap text-sm">
                                {{ isset($row['values'][$metric]) ? number_format($row['values'][$metric], $series[$metric]['decimals']) : '--' }}
                                <span class="font-sans text-[10px] font-normal text-telemetry-slate">{{ $series[$metric]['unit'] }}</span>
                            </span>
                        @endforeach
                    </div>
                </div>
            @empty
                <div class="px-5 py-6 text-center text-sm text-telemetry-slate">Belum ada data</div>
            @endforelse
        </x-card>
    </div>

</div>
