@props(['active'])

@php
$classes = ($active ?? false)
            ? 'block w-full px-4 py-2.5 rounded-2xl text-start text-base font-bold bg-gray-900 dark:bg-white text-white dark:text-gray-900 transition'
            : 'block w-full px-4 py-2.5 rounded-2xl text-start text-base font-semibold text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 transition';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
