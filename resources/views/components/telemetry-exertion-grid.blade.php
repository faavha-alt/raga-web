@props([
    'weeks' => [],
    'metricLabel' => 'Relative Effort',
    'activeDays' => 0,
    'totalScore' => 0,
    'start' => null,
    'end' => null,
])

@php
    // 5 tingkat intensitas dari well (kosong) menuju ember (puncak). Warna
    // literal, bukan gradien, supaya tiap sel terbaca sebagai satu nilai diskret.
    $palette = ['#F1F3F5', '#FFE1D9', '#FFB8A3', '#FF8A66', '#FF5C33', '#FF3E1D'];
@endphp

{{-- Grid exertion 364 hari (kolom = minggu, baris = Senin–Minggu), SVG/HTML murni
     tanpa library JS. --}}
<div {{ $attributes->merge(['class' => '']) }}>
    <div class="overflow-x-auto pb-1">
        <div class="grid grid-flow-col auto-cols-[10px] grid-rows-[repeat(7,10px)] gap-[3px]">
            @foreach ($weeks as $week)
                @foreach ($week as $day)
                    @if ($day['date'] === null)
                        <span class="h-[10px] w-[10px]" aria-hidden="true"></span>
                    @else
                        <span
                            class="h-[10px] w-[10px]"
                            style="background-color: {{ $palette[$day['level']] }}"
                            title="{{ $day['date'] }} · {{ $day['value'] }} {{ $metricLabel }}"
                        ></span>
                    @endif
                @endforeach
            @endforeach
        </div>
    </div>

    <div class="mt-3 flex flex-wrap items-center justify-between gap-x-6 gap-y-2">
        <div class="flex items-center gap-1.5">
            <span class="telemetry-label">Less</span>
            @foreach ($palette as $color)
                <span class="h-[10px] w-[10px]" style="background-color: {{ $color }}"></span>
            @endforeach
            <span class="telemetry-label">More</span>
        </div>

        <div class="flex flex-wrap gap-x-5 gap-y-1">
            <span class="telemetry-label">{{ number_format($activeDays) }} hari aktif</span>
            <span class="telemetry-label">Total {{ number_format($totalScore, 0) }} {{ $metricLabel }}</span>
            @if ($start && $end)
                <span class="telemetry-label">{{ $start }} → {{ $end }}</span>
            @endif
        </div>
    </div>
</div>
