<button {{ $attributes->merge(['type' => 'button', 'class' => 'inline-flex items-center justify-center gap-2 rounded-full border-2 border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 px-6 py-2.5 text-sm font-bold text-gray-700 dark:text-gray-200 transition duration-150 ease-in-out hover:border-raga-primary hover:text-raga-primary focus:outline-none focus:ring-2 focus:ring-raga-primary focus:ring-offset-2 disabled:opacity-25']) }}>
    {{ $slot }}
</button>
