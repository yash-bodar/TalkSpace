import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Plus Jakarta Sans', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                brand: {
                    50: '#fdf2f9',
                    100: '#fce7f4',
                    200: '#f9cfea',
                    300: '#f4a7d7',
                    400: '#ec70bc',
                    500: '#df429f',
                    600: '#d91a8d', // Primary Vibrant Logo Magenta
                    700: '#b0106d',
                    800: '#8b1e7c', // Secondary Logo Violet
                    900: '#690e4f',
                    950: '#3d0a66', // Deep Cosmic Violet
                },
                dark: {
                    surface: '#0B0813',
                    card: '#130E1F',
                    panel: '#1A132A',
                    border: 'rgba(217, 26, 141, 0.15)',
                }
            },
        },
    },

    plugins: [forms],
};
