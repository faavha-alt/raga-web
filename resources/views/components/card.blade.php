@props(['class' => ''])

<div {{ $attributes->merge(['class' => "bg-white dark:bg-gray-800 rounded-2xl p-6 shadow-sm $class"]) }}>
    {{ $slot }}
</div>
