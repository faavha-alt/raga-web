@props(['series'])

@php
    $maxDistance = max(1, collect($series)->max('distance_meters'));
@endphp

<div class="flex items-end justify-between gap-2 h-32 px-1" role="img" aria-label="Jarak latihan per hari minggu ini">
    @foreach ($series as $day)
        @php
            $km = $day['distance_meters'] / 1000;
            $heightPercent = $day['distance_meters'] > 0 ? max(6, ($day['distance_meters'] / $maxDistance) * 100) : 0;
        @endphp
        <div class="flex-1 flex flex-col items-center gap-1.5" title="{{ $day['label'] }}: {{ number_format($km, 1) }} km ({{ $day['count'] }} aktivitas)">
            <span class="text-[10px] font-bold text-gray-500 dark:text-gray-400 tabular-nums">
                @if ($day['distance_meters'] > 0){{ number_format($km, 1) }}@endif
            </span>
            <div class="w-full flex items-end h-20 rounded-t-md overflow-hidden bg-gray-50 dark:bg-gray-900/40">
                <div
                    class="w-full rounded-t-[4px] transition-all"
                    style="height: {{ $heightPercent }}%; background-color: {{ $day['distance_meters'] > 0 ? '#6C5CE7' : 'transparent' }};"
                ></div>
            </div>
            <span class="text-[10px] font-bold uppercase text-gray-400">{{ $day['label'] }}</span>
        </div>
    @endforeach
</div>
