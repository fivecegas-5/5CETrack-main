import path from 'node:path';
import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import tailwindStylesSource from './resources/js/react/tailwindEntry.js';

const tailwindCssVirtualImport = 'virtual:fivece-tailwind.css?inline';
const tailwindCssInlineId = `${path.resolve('resources/js/react/tailwindEntry.css').replace(/\\/g, '/')}?inline`;

const tailwindCssSourcePlugin = {
    name: 'fivece-tailwind-react-source',
    enforce: 'pre',
    resolveId(source) {
        if (source === tailwindCssVirtualImport) {
            return tailwindCssInlineId;
        }
    },
    load(id) {
        if (id.replace(/\\/g, '/') === tailwindCssInlineId) {
            return tailwindStylesSource;
        }
    },
};

export default defineConfig({
    plugins: [
        react(),
        laravel({
            input: ['resources/js/app.js'],
            refresh: true,
        }),
        tailwindCssSourcePlugin,
        tailwindcss(),
    ],
    build: {
        rollupOptions: {
            output: {
                manualChunks(id) {
                    if (id.includes('/resources/js/react/styles.jsx')) {
                        return 'fivece-styles';
                    }
                },
            },
        },
    },
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
