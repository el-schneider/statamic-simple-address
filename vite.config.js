import statamic from '@statamic/cms/vite-plugin'
import laravel from 'laravel-vite-plugin'
import { defineConfig } from 'vite'

export default defineConfig({
  plugins: [
    laravel({
      input: ['resources/js/simple-address.js'],
      publicDirectory: 'resources/dist',
    }),
    statamic(),
  ],
})
