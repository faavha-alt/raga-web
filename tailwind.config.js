import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: 'media',

    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                raga: {
                    accent: '#21A08C',
                    excellent: '#2A9968',
                    good: '#4A8FD9',
                    moderate: '#D9992A',
                    low: '#D14747',
                },
            },
        },
    },

    plugins: [forms],
};
