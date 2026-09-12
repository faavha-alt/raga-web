@props(['value'])

<label {{ $attributes->merge(['class' => 'mb-1.5 block font-display text-[10px] font-bold uppercase tracking-[0.12em] text-telemetry-slate']) }}>
    {{ $value ?? $slot }}
</label>
