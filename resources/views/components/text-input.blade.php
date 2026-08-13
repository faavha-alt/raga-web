@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'w-full rounded-2xl border-2 border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 px-4 py-3 text-sm font-medium text-gray-900 dark:text-gray-100 placeholder:text-gray-400 focus:border-raga-primary focus:bg-white dark:focus:bg-gray-800 focus:ring-raga-primary transition']) }}>
