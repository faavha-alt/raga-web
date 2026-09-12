@props(['variant' => 'neutral'])

@php
    // Chip status mikro (tinggi 20px) bergaya instrumen: latar low-saturation,
    // border tipis, teks uppercase. `recovery` untuk status pulih, `pace` untuk
    // pace/lap, `strain` untuk beban kritis, `neutral` untuk sisanya.
    $variants = [
        'recovery' => 'bg-[rgba(0,184,101,0.08)] text-telemetry-emerald-deep border-telemetry-emerald/20',
        'pace' => 'bg-[rgba(0,112,243,0.08)] text-telemetry-chrono-deep border-telemetry-chrono/20',
        'strain' => 'bg-[rgba(255,62,29,0.08)] text-telemetry-ember-deep border-telemetry-ember/25',
        'neutral' => 'bg-telemetry-well text-telemetry-slate border-telemetry-line',
    ];

    $tone = $variants[$variant] ?? $variants['neutral'];
@endphp

<span {{ $attributes->merge(['class' => "inline-flex h-5 items-center border px-2 text-[10px] font-bold uppercase tracking-[0.08em] $tone"]) }}>
    {{ $slot }}
</span>
