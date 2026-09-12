@props(['active' => false, 'variant' => 'primary'])

@php
    // Link navigasi gaya telemetry: uppercase, tracking lebar, font display.
    // `primary` = baris utama (aktif ditandai blok gelap + titik ember),
    // `sub`    = strip sekunder (aktif ditandai teks penuh, tanpa blok).
    $base = 'inline-flex items-center gap-1.5 whitespace-nowrap font-display text-[11px] font-bold uppercase tracking-[0.08em] transition-colors';

    $classes = match (true) {
        $variant === 'sub' && $active => $base.' text-telemetry-ink',
        $variant === 'sub' => $base.' text-telemetry-slate hover:text-telemetry-ink',
        $active => $base.' rounded bg-telemetry-ink px-3 py-1.5 text-white',
        default => $base.' rounded px-3 py-1.5 text-telemetry-slate hover:bg-telemetry-well hover:text-telemetry-ink',
    };
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    @if ($active && $variant !== 'sub')
        <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-telemetry-ember" aria-hidden="true"></span>
    @endif
    {{ $slot }}
</a>
