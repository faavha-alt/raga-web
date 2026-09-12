import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    // Class-based rather than OS-media-based: nothing in the app currently
    // toggles a `dark` class, so this makes every `dark:` variant inert and
    // the app renders its light-mode design consistently regardless of the
    // visitor's OS theme. The auth/welcome pages' dark look is hardcoded
    // (not `dark:` variants), so they're unaffected by this.
    darkMode: 'class',

    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                // Inter untuk teks/prosa, Space Grotesk untuk angka & label
                // telemetry. `sans` diarahkan ke Inter supaya body copy ikut
                // design system baru tanpa menyentuh setiap view.
                sans: ['Inter', ...defaultTheme.fontFamily.sans],
                display: ['Space Grotesk', ...defaultTheme.fontFamily.sans],
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
                // Swiss Telemetry Sport — acuan_tampilan/swiss_telemetry_sport/DESIGN.md.
                // `ember` hanya untuk puncak beban/aksi, `chrono` untuk pace,
                // `emerald` untuk recovery, `amber` untuk warning.
                telemetry: {
                    canvas: '#F8F9FA',
                    surface: '#FFFFFF',
                    well: '#F1F3F5',
                    line: '#E2E8F0',
                    'line-strong': '#DEE2E6',
                    ink: '#0D1117',
                    steel: '#1A1F2C',
                    slate: '#495057',
                    ember: '#FF3E1D',
                    'ember-dark': '#E03214',
                    'ember-deep': '#D62C0D',
                    chrono: '#0070F3',
                    'chrono-deep': '#0055B8',
                    emerald: '#00B865',
                    'emerald-deep': '#008A4B',
                    amber: '#F59E0B',
                },
            },
            boxShadow: {
                glow: '0 20px 60px -20px rgba(108, 92, 231, 0.35)',
                'glow-accent': '0 20px 60px -20px rgba(33, 160, 140, 0.35)',
                // Hanya untuk overlay/menu — kartu memakai outline, bukan shadow.
                overlay: '0 8px 30px rgba(13, 17, 23, 0.04)',
            },
        },
    },

    plugins: [forms],
};
