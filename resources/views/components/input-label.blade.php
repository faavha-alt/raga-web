@props(['value'])

<label {{ $attributes->merge(['class' => 'block text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1.5']) }}>
    {{ $value ?? $slot }}
</label>
