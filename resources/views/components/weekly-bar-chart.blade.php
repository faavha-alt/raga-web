@props(['series'])

@php
    $maxDistance = max(1, collect($series)->max('distance_meters'));
@endphp

<div class="flex h-32 items-end justify-between gap-2 px-1" role="img" aria-label="Jarak latihan per hari minggu ini">
    @foreach ($series as $day)
        @php
            $km = $day['distance_meters'] / 1000;
            $heightPercent = $day['distance_meters'] > 0 ? max(6, ($day['distance_meters'] / $maxDistance) * 100) : 0;
        @endphp
        <div class="flex flex-1 flex-col items-center gap-1.5" title="{{ $day['label'] }}: {{ number_format($km, 1) }} km ({{ $day['count'] }} aktivitas)">
            <span class="telemetry-value text-[10px]">
                @if ($day['distance_meters'] > 0){{ number_format($km, 1) }}@endif
            </span>
            <div class="flex h-20 w-full items-end overflow-hidden border-b border-telemetry-line bg-telemetry-well">
                <div
                    class="w-full transition-all"
                    style="height: {{ $heightPercent }}%; background-color: {{ $day['distance_meters'] > 0 ? '#FF3E1D' : 'transparent' }};"
                ></div>
            </div>
            <span class="telemetry-label">{{ $day['label'] }}</span>
        </div>
    @endforeach
</div>
