<template>
  <div>
    <!-- Address Select -->
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

    <!-- Details Toggle Button -->
    <div class="mb-2 mt-0.5 flex justify-end text-right">
      <button
        v-if="value"
        type="button"
        class="mr-1 text-xs hover:text-blue-700 hover:underline"
        @click="toggleDetails"
      >
        {{ detailsButtonText }}
      </button>
    </div>

    <!-- Address Details Panel -->
    <address-details-panel v-if="showDetails && value" :address="value" />
  </div>
</template>

<script>
import vSelect from 'vue-select'
import debounce from './utils/debounce'
import AddressDetailsPanel from './components/AddressDetailsPanel.vue'

// Constants
const DEFAULT_LANGUAGE = 'en'
const DEFAULT_DEBOUNCE_DELAY = 300

export default {
  name: 'SimpleAddressField',

  components: {
    vSelect,
    AddressDetailsPanel,
  },

  mixins: [Fieldtype],

  data() {
    return {
      options: [],
      debouncedSearchFunction: null,
      showDetails: false,
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

    detailsButtonText() {
      return this.__('details')
    },

    searchConfig() {
      const { countries, language, debounce_delay } = this.config
      const fieldDebounceDelay = debounce_delay || DEFAULT_DEBOUNCE_DELAY
      const providerMinDelay = this.meta.provider_min_debounce_delay || 0

      return {
        countries: countries || [],
        language: language || DEFAULT_LANGUAGE,
        debounceDelay: Math.max(fieldDebounceDelay, providerMinDelay),
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
    'meta.provider_min_debounce_delay'() {
      this.debouncedSearchFunction = null
    },
  },

  methods: {
    setSelected(value) {
      this.update(value)
      // Close details panel when a new address is selected
      this.showDetails = false
    },

    toggleDetails() {
      this.showDetails = !this.showDetails
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
        const response = await this.performAddressSearch(search)
        this.options = this.processSearchResults(response.results || [])
      } catch (error) {
        this.handleSearchError(error)
      } finally {
        loading(false)
      }
    },

    async performAddressSearch(query) {
      const { countries, language } = this.searchConfig
      const { provider } = this.meta

      const payload = {
        query,
        provider,
        additional_exclude_fields: this.meta.additional_exclude_fields || [],
        countries,
        language: Array.isArray(language) ? language.join(',') : language,
      }

      const response = await Statamic.$axios.post('/cp/simple-address/search', payload)
      return response.data
    },

    processSearchResults(data) {
      return data.map((item) => ({
        label: item.label,
        value: item,
      }))
    },

    handleSearchError(error) {
      console.error('Address search failed:', error)

      const message = error.response?.data?.message || this.__('Failed to search addresses. Please try again.')
      this.$toast.error(this.__(message))
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
