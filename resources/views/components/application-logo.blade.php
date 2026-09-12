@props(['iconOnly' => false])

<div {{ $attributes->class(['inline-flex items-center gap-2']) }}>
    <span class="relative flex h-9 w-9 shrink-0 items-center justify-center rounded bg-telemetry-ink font-display text-base font-bold text-white">
        R
    </span>
    @unless ($iconOnly)
        <span class="font-display text-lg font-bold tracking-tight text-telemetry-ink">RAGA</span>
    @endunless
</div>
