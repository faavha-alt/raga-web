@props(['icon', 'label', 'value' => null, 'unit' => null])

<x-card class="!p-4">
    <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500">{{ $icon }} {{ $label }}</p>
    <p class="mt-1 text-2xl font-black text-gray-900 dark:text-gray-100">
        {{ $value ?? '--' }}@if ($value !== null && $unit)<span class="text-sm font-semibold text-gray-400"> {{ $unit }}</span>@endif
    </p>
</x-card>
