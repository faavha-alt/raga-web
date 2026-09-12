@props(['active'])

@php
$classes = ($active ?? false)
            ? 'inline-flex items-center rounded px-3 py-2 font-display text-[11px] font-bold uppercase tracking-[0.08em] bg-telemetry-ink text-white transition-colors'
            : 'inline-flex items-center rounded px-3 py-2 font-display text-[11px] font-bold uppercase tracking-[0.08em] text-telemetry-slate transition-colors hover:bg-telemetry-well hover:text-telemetry-ink';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
