import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { globSync } from 'node:fs';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/js/app.js',
                'resources/js/lifestream/home.js',
                'resources/js/lifestream/heatmap.js',
                // Images referenced from Blade with Vite::asset().
                ...globSync('resources/images/**/*.{png,ico,svg}'),
            ],
            refresh: true,
        }),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
