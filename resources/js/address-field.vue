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
import debounce from 'lodash.debounce'

// Constants
const NOMINATIM_BASE_URL = 'https://nominatim.openstreetmap.org/search'
const DEFAULT_LANGUAGE = 'en'

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
      const { countries, language, exclude_fields, debounce_delay } = this.config
      return {
        countries: countries || [],
        language: language || DEFAULT_LANGUAGE,
        excludeFields: exclude_fields || [],
        debounceDelay: debounce_delay || 1000,
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

    buildSearchUrl(query) {
      const { countries, language } = this.searchConfig

      const params = new URLSearchParams({
        q: query,
        addressdetails: '1',
        namedetails: '1',
        format: 'json',
        'accept-language': language,
      })

      if (countries.length > 0) {
        params.set('countrycodes', countries.join(','))
      }

      return `${NOMINATIM_BASE_URL}?${params}`
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
