import js from '@eslint/js'
import pluginVue from 'eslint-plugin-vue'
import { defineConfig, globalIgnores } from 'eslint/config'
import globals from 'globals'
import prettier from 'eslint-config-prettier'

export default defineConfig([
  globalIgnores(['**/dist/**', '**/node_modules/**', '**/vendor/**', 'vendor/**']),
  {
    files: ['**/*.{js,vue}'],
  },
  js.configs.recommended,
  ...pluginVue.configs['flat/vue2-recommended'],
  {
    languageOptions: {
      sourceType: 'module',
      globals: {
        ...globals.browser,
        ...globals.node,
        Statamic: 'readonly',
        __: 'readonly',
        Fieldtype: 'readonly',
      },
    },
    rules: {
      'vue/no-side-effects-in-computed-properties': 'off',
      'vue/no-use-computed-property-like-method': 'off',
    },
  },
  prettier,
])
