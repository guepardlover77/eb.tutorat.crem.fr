import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
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
                brand: {
                    red: '#CC2929',
                    'red-dark': '#A81E1E',
                    'red-light': '#E8453E',
                    green: '#3A8C3A',
                    'green-dark': '#2D6E2D',
                    'green-light': '#4CAF50',
                    dark: '#1C1C1C',
                    gray: '#F5F5F5',
                },
            },
        },
    },

    plugins: [forms],
};
