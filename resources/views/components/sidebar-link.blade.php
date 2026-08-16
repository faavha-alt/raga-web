@props(['active' => false, 'icon' => ''])

@php
$classes = ($active ?? false)
    ? 'flex items-center gap-3 rounded-2xl px-3 py-2.5 text-sm font-bold bg-gray-900 dark:bg-white text-white dark:text-gray-900 transition'
    : 'flex items-center gap-3 rounded-2xl px-3 py-2.5 text-sm font-semibold text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white hover:bg-gray-100 dark:hover:bg-gray-800 transition';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    <span class="text-base leading-none w-5 text-center shrink-0">{{ $icon }}</span>
    <span class="truncate">{{ $slot }}</span>
</a>
