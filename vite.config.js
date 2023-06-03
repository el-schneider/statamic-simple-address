
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue2';
// https://vitejs.dev/config/
export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/js/simple-address.js',
            ],
            publicDirectory: 'resources/dist',
        }),
        vue(),
    ],
})
