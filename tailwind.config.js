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
                sans: ['Plus Jakarta Sans', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                raga: {
                    accent: '#21A08C',
                    primary: '#6C5CE7',
                    energy: '#FF6B57',
                    excellent: '#2A9968',
                    good: '#4A8FD9',
                    moderate: '#D9992A',
                    low: '#D14747',
                },
            },
            boxShadow: {
                glow: '0 20px 60px -20px rgba(108, 92, 231, 0.35)',
                'glow-accent': '0 20px 60px -20px rgba(33, 160, 140, 0.35)',
            },
        },
    },

    plugins: [forms],
};
