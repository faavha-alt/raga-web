@props(['items'])

{{-- items: list<{label, icon, value, secondary, percent}>, pre-sorted by the caller --}}

<div class="space-y-3">
    @forelse ($items as $item)
        <div>
            <div class="flex items-center justify-between text-sm mb-1">
                <span class="font-bold text-gray-700 flex items-center gap-1.5">
                    @if (!empty($item['icon']))<span>{{ $item['icon'] }}</span>@endif
                    {{ $item['label'] }}
                </span>
                <span class="text-xs font-semibold text-gray-400 tabular-nums">
                    {{ $item['value'] }}
                    @if (!empty($item['secondary']))
                        <span class="text-gray-300">· {{ $item['secondary'] }}</span>
                    @endif
                </span>
            </div>
            <div class="h-2.5 w-full rounded-full bg-gray-100 overflow-hidden">
                <div class="h-full rounded-full bg-raga-primary" style="width: {{ max(2, $item['percent']) }}%"></div>
            </div>
        </div>
    @empty
        <p class="text-sm text-gray-400 text-center py-4">Belum ada data untuk periode ini.</p>
    @endforelse
</div>
