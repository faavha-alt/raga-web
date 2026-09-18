@props(['iconOnly' => false])

<div {{ $attributes->class(['inline-flex items-center gap-2']) }}>
    <span class="relative flex h-8 w-8 shrink-0 items-center justify-center rounded bg-telemetry-ink font-display text-sm font-bold text-white lg:h-9 lg:w-9 lg:text-base">
        R
    </span>
    @unless ($iconOnly)
        <span class="font-display text-base font-bold tracking-tight text-telemetry-ink lg:text-lg">RAGA</span>
    @endunless
</div>
