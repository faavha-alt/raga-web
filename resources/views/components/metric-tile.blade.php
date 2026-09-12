@props(['icon' => null, 'label', 'value' => null, 'unit' => null])

{{-- Metric cell gaya telemetry: label mikro uppercase di atas, angka tabular
     besar di bawah, unit kecil menempel di baseline. --}}
<div {{ $attributes->merge(['class' => 'bg-telemetry-surface border border-telemetry-line p-4']) }}>
    <div class="flex items-center gap-2">
        @if ($icon)
            <span class="text-sm leading-none" aria-hidden="true">{{ $icon }}</span>
        @endif
        <p class="telemetry-label">{{ $label }}</p>
    </div>
    <p class="mt-2.5 text-[28px] leading-none telemetry-value">
        {{ $value ?? '--' }}@if ($value !== null && $unit)<span class="ml-1.5 text-[10px] font-semibold uppercase tracking-[0.08em] text-telemetry-slate">{{ $unit }}</span>@endif
    </p>
</div>
