@props(['iconOnly' => false])

<div {{ $attributes->class(['inline-flex items-center gap-2']) }}>
    <span class="relative flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-raga-accent to-raga-primary text-white font-black text-base shadow-glow">
        R
    </span>
    @unless ($iconOnly)
        <span class="text-lg font-extrabold tracking-tight text-gray-900 dark:text-white">RAGA</span>
    @endunless
</div>
