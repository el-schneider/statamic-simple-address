import vue from '@vitejs/plugin-vue'
import laravel from 'laravel-vite-plugin'
import { defineConfig } from 'vite'
import * as Vue from 'vue'

// Based on @statamic/cms vite-plugin/externals.js
// Only handles Vue - @statamic/cms is resolved from the linked package
function statamicExternals() {
  const RESOLVED_VIRTUAL_MODULE_ID = '\0vue-external'
  const vueExports = Object.keys(Vue).filter((key) => key !== 'default' && /^[a-zA-Z_$][a-zA-Z0-9_$]*$/.test(key))

  return {
    name: 'statamic-externals',
    enforce: 'pre',

    resolveId(id) {
      if (id === 'vue') {
        return RESOLVED_VIRTUAL_MODULE_ID
      }
      return null
    },

    load(id) {
      if (id === RESOLVED_VIRTUAL_MODULE_ID) {
        const exportsList = vueExports.join(', ')
        return `
          const Vue = window.Vue;
          export default Vue;
          export const { ${exportsList} } = Vue;
        `
      }
      return null
    },

    // The virtual module above ensures Vue imports resolve to window.Vue.
  }
}

export default defineConfig({
  plugins: [
    laravel({
      input: ['resources/js/simple-address.js'],
      publicDirectory: 'resources/dist',
    }),
    statamicExternals(),
    vue(),
  ],
})
