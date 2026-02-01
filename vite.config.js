import vue from '@vitejs/plugin-vue'
import laravel from 'laravel-vite-plugin'
import tailwindcss from '@tailwindcss/vite'
import { defineConfig } from 'vite'
import * as Vue from 'vue'

/**
 * Statamic externals plugin - ensures Vue imports resolve to window.Vue
 * at runtime, preventing Vue from being bundled into addon code.
 *
 * Based on @statamic/cms vite-plugin/externals.js
 */
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

    configResolved(resolvedConfig) {
      resolvedConfig.build.rollupOptions.plugins = resolvedConfig.build.rollupOptions.plugins || []
      resolvedConfig.build.rollupOptions.plugins.push({
        name: 'statamic-externals',
        renderChunk(code) {
          // Handle mixed imports: import Default, { named } from 'vue'
          code = code.replace(
            /import\s+([a-zA-Z_$][a-zA-Z0-9_$]*)\s*,\s*(\{[^}]+\})\s+from\s+['"]vue['"];?/g,
            'const $1 = window.Vue;\nconst $2 = window.Vue;',
          )

          // Handle remaining imports (default or named only)
          return code.replace(/import\s+(.+?)\s+from\s+['"]vue['"];?/g, 'const $1 = window.Vue;')
        },
      })
    },
  }
}

export default defineConfig({
  server: {
    host: 'localhost',
  },
  plugins: [
    laravel({
      input: ['resources/js/simple-address.js', 'resources/css/addon.css'],
      publicDirectory: 'resources/dist',
    }),
    tailwindcss(),
    statamicExternals(),
    vue(),
  ],
})
