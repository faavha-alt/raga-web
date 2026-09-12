@props(['status'])

@if ($status)
    <div {{ $attributes->merge(['class' => 'text-sm font-semibold text-telemetry-emerald-deep']) }}>
        {{ $status }}
    </div>
@endif
