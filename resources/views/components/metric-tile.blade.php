@props(['icon', 'label', 'value' => null, 'unit' => null])

<div class="rounded-2xl bg-white border border-gray-100 p-4 shadow-[0_1px_2px_rgba(16,24,40,0.04)]">
    <div class="flex items-center gap-2">
        <span class="flex h-7 w-7 items-center justify-center rounded-full bg-gray-50 text-sm">{{ $icon }}</span>
        <p class="text-[11px] font-bold uppercase tracking-wider text-gray-400">{{ $label }}</p>
    </div>
    <p class="mt-2.5 text-[26px] leading-none font-black text-gray-900">
        {{ $value ?? '--' }}@if ($value !== null && $unit)<span class="text-sm font-semibold text-gray-400"> {{ $unit }}</span>@endif
    </p>
</div>
