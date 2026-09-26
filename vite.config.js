import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/cabinet.css', 'resources/js/cabinet.js'],
            refresh: true,
        }),
    ],
});
