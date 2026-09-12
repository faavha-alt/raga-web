@props(['items'])

{{-- items: list<{label, icon, value, secondary, percent}>, pre-sorted by the caller --}}

<div class="space-y-3">
    @forelse ($items as $item)
        <div>
            <div class="mb-1.5 flex items-center justify-between gap-3 text-sm">
                <span class="flex items-center gap-1.5 font-semibold text-telemetry-ink">
                    @if (!empty($item['icon']))<span aria-hidden="true">{{ $item['icon'] }}</span>@endif
                    {{ $item['label'] }}
                </span>
                <span class="telemetry-value text-xs">
                    {{ $item['value'] }}
                    @if (!empty($item['secondary']))
                        <span class="font-sans font-normal text-telemetry-slate">· {{ $item['secondary'] }}</span>
                    @endif
                </span>
            </div>
            <div class="h-2 w-full overflow-hidden border border-telemetry-line bg-telemetry-well">
                <div class="h-full bg-telemetry-ember" style="width: {{ max(2, $item['percent']) }}%"></div>
            </div>
        </div>
    @empty
        <p class="py-6 text-center text-sm text-telemetry-slate">Belum ada data untuk periode ini.</p>
    @endforelse
</div>
