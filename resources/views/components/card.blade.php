@props(['class' => ''])

<div {{ $attributes->merge(['class' => "bg-white dark:bg-gray-800 rounded-3xl p-6 border border-gray-100 dark:border-gray-700/60 shadow-sm hover:shadow-md transition-shadow duration-200 $class"]) }}>
    {{ $slot }}
</div>
