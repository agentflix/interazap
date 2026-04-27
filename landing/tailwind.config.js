/** @type {import('tailwindcss').Config} */
module.exports = {
    content: ['./*.html'],
    theme: {
        extend: {
            colors: {
                primary: '#1a3c34',
                'primary-light': '#245044',
                accent: '#22c55e',
                'accent-hover': '#16a34a',
                dark: '#1f2937',
                light: '#f9fafb',
                muted: '#6b7280',
            },
            fontFamily: {
                sans: ['Inter', 'Plus Jakarta Sans', 'system-ui', '-apple-system', 'sans-serif'],
            },
        },
    },
    plugins: [],
};
