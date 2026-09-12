@props(['active'])

@php
$classes = ($active ?? false)
            ? 'block w-full rounded px-3 py-2 text-start text-base font-bold bg-telemetry-ink text-white transition-colors'
            : 'block w-full rounded px-3 py-2 text-start text-base font-semibold text-telemetry-slate transition-colors hover:bg-telemetry-well hover:text-telemetry-ink';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
