import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/js/player.js',
                'resources/js/tuner.js',
                'resources/js/dashboard.js',
                'resources/js/session-report.js',
                'resources/js/piece-stats.js',
                'resources/js/score-preview.js',
                'resources/js/pdf-player.js',
            ],
            refresh: true,
        }),
    ],
});
