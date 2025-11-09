<template>
  <div>
    <v-select
      ref="select"
      :value="value"
      :filterable="false"
      :options="options"
      :placeholder="config.placeholder"
      :components="selectComponents"
      append-to-body
      :no-drop="!options.length"
      @search="onSearch"
      @input="setSelected"
    >
      <template #option="{ label }">
        <div v-text="label" />
      </template>
      <template #selected-option="{ label }">
        <div v-text="label" />
      </template>
      <template #no-options>
        <div class="px-4 py-2 text-sm text-gray-700 ltr:text-left rtl:text-right" v-text="noOptionsText" />
      </template>
    </v-select>
  </div>
</template>

<script>
import vSelect from 'vue-select'
import debounce from './utils/debounce'

// Constants
const DEFAULT_LANGUAGE = 'en'
const DEFAULT_DEBOUNCE_DELAY = 1000

export default {
  name: 'SimpleAddressField',

  components: {
    vSelect,
  },

  mixins: [Fieldtype],

  data() {
    return {
      options: [],
      debouncedSearchFunction: null,
    }
  },

  computed: {
    selectComponents() {
      return {
        Deselect: this.deselectComponent,
        OpenIndicator: this.openIndicatorComponent,
      }
    },

    deselectComponent() {
      return {
        render: (createElement) => createElement('span', '×'),
      }
    },

    openIndicatorComponent() {
      return {
        render: (createElement) =>
          createElement('span', {
            class: { toggle: true },
            domProps: {
              innerHTML: this.chevronDownIcon,
            },
          }),
      }
    },

    chevronDownIcon() {
      return `<svg xmlns="http://www.w3.org/2000/svg" height="16" width="16" viewBox="0 0 20 20">
        <path fill="currentColor" d="M9.293 12.95l.707.707L15.657 8l-1.414-1.414L10 10.828 5.757 6.586 4.343 8z"/>
      </svg>`
    },

    noOptionsText() {
      return this.__('No options to choose from.')
    },

    searchConfig() {
      const { countries, language, exclude_fields, debounce_delay, provider_config } = this.config
      return {
        countries: countries || [],
        language: language || DEFAULT_LANGUAGE,
        excludeFields: exclude_fields || [],
        debounceDelay: debounce_delay || DEFAULT_DEBOUNCE_DELAY,
        providerConfig: provider_config || {},
      }
    },

    debouncedSearch() {
      if (!this.debouncedSearchFunction) {
        this.debouncedSearchFunction = debounce(this.performSearch.bind(this), this.searchConfig.debounceDelay)
      }
      return this.debouncedSearchFunction
    },
  },

  watch: {
    'searchConfig.debounceDelay'() {
      this.debouncedSearchFunction = null
    },
  },

  methods: {
    setSelected(value) {
      this.update(value)
    },

    onSearch(search, loading) {
      if (!search?.length) {
        this.options = []
        loading(false)
        return
      }

      loading(true)
      this.debouncedSearch(loading, search)
    },

    async performSearch(loading, search) {
      try {
        const results = await this.performAddressSearch(search)
        this.options = this.processSearchResults(results)
      } catch (error) {
        this.handleSearchError(error)
      } finally {
        loading(false)
      }
    },

    async performAddressSearch(query) {
      const url = this.buildSearchUrl(query)

      const response = await fetch(url, {
        headers: {
          Accept: 'application/json',
        },
      })

      if (!response.ok) {
        throw new Error(`API request failed: ${response.status} ${response.statusText}`)
      }

      return response.json()
    },

    /**
     * Build search URL with parameters from provider configuration
     * @param {string} query - Search query
     * @returns {string} Complete search URL
     */
    buildSearchUrl(query) {
      const { providerConfig } = this.searchConfig
      const { countries, language } = this.searchConfig

      if (!providerConfig || !providerConfig.base_url) {
        throw new Error('Provider configuration not properly loaded')
      }

      const params = new URLSearchParams({
        [providerConfig.freeform_search_key]: query,
        ...providerConfig.request_options,
        'accept-language': language,
      })

      if (countries.length > 0) {
        params.set('countrycodes', countries.join(','))
      }

      // Add API key if provider requires it
      if (providerConfig.api_key && providerConfig.api_key_param_name) {
        params.set(providerConfig.api_key_param_name, providerConfig.api_key)
      }

      return `${providerConfig.base_url}?${params}`
    },

    processSearchResults(data) {
      return data.map((item) => this.processSearchItem(item))
    },

    processSearchItem(item) {
      const processedItem = {
        label: item.display_name,
        ...item,
      }

      this.searchConfig.excludeFields.forEach((field) => {
        if (processedItem[field]) {
          delete processedItem[field]
        }
      })

      if (processedItem.namedetails) {
        this.cleanNameDetails(processedItem.namedetails)
      }

      return processedItem
    },

    cleanNameDetails(namedetails) {
      Object.keys(namedetails).forEach((key) => {
        if (key.includes(':')) {
          delete namedetails[key]
        }
      })
    },

    handleSearchError(error) {
      console.error('Address search failed:', error)
      this.$toast.error(this.__('Failed to search addresses. Please try again.'))
    },
  },
}
</script>

<style scoped>
/* Remove webkit search cancel button */
:deep(.vs__search::-webkit-search-cancel-button) {
  appearance: none;
}
</style>
