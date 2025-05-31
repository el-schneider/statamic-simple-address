import vue from '@vitejs/plugin-vue2'
import laravel from 'laravel-vite-plugin'
import { defineConfig } from 'vite'
// https://vitejs.dev/config/
export default defineConfig({
  plugins: [
    laravel({
      input: ['resources/js/simple-address.js'],
      publicDirectory: 'resources/dist',
    }),
    vue(),
  ],
})
