import laravel from 'laravel-vite-plugin'
import statamic from '@statamic/cms/vite-plugin'
import tailwindcss from '@tailwindcss/vite'
import { defineConfig } from 'vite'

export default defineConfig({
  plugins: [
    laravel({
      input: ['resources/js/simple-address.js', 'resources/css/addon.css'],
      publicDirectory: 'resources/dist',
    }),
    tailwindcss(),
    statamic(),
  ],
})

