@props(['class' => ''])

<div {{ $attributes->merge(['class' => "bg-telemetry-surface rounded-lg p-6 border border-telemetry-line transition-colors hover:border-telemetry-line-strong $class"]) }}>
    {{ $slot }}
</div>
