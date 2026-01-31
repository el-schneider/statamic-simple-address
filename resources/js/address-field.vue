<template>
  <div class="simple-address-field">
    <!-- Address Combobox with inline details button -->
    <div class="simple-address-select-wrapper">
      <Combobox
        v-model="selectedValue"
        :options="options"
        :placeholder="config.placeholder || __('Search for an address...')"
        :searchable="true"
        :ignore-filter="true"
        :clearable="true"
        option-label="label"
        option-value="value"
        @search="onSearch"
      >
        <template #option="option">
          <div v-text="option.label" />
        </template>
        <template #no-options="{ searchQuery }">
          <div class="px-2 py-1.5 text-sm text-gray-500 dark:text-gray-400">
            {{ searchQuery ? __('No addresses found.') : __('Type to search for an address...') }}
          </div>
        </template>
      </Combobox>

      <!-- Details Toggle Button -->
      <button v-if="value" type="button" class="simple-address-details-btn" @click.stop="toggleDetails">
        {{ __('details') }}
      </button>
    </div>

    <!-- Address Details Panel -->
    <AddressDetailsPanel
      v-if="showDetails && value"
      ref="detailsPanel"
      :address="value"
      @coordinates-changed="onCoordinatesChanged"
    />
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { Fieldtype } from '@statamic/cms'
import { Combobox } from '@statamic/cms/ui'
import AddressDetailsPanel from './components/AddressDetailsPanel.vue'

// Constants
const DEFAULT_LANGUAGE = 'en'
const DEBOUNCE_DELAY = 300

// Fieldtype setup
const emit = defineEmits(Fieldtype.emits)
const props = defineProps(Fieldtype.props)
const { expose, update } = Fieldtype.use(emit, props)
defineExpose(expose)

// Local state
const options = ref([])
const showDetails = ref(false)
const detailsPanel = ref(null)
const searchTimeout = ref(null)

// Computed
const selectedValue = computed({
  get() {
    if (!props.value) return null
    // Return the value in the format Combobox expects
    return props.value
  },
  set(newValue) {
    // When selection changes, update the field value
    // newValue will be the option.value (the address object)
    update(newValue)
    showDetails.value = false
  },
})

const searchConfig = computed(() => {
  const { countries, language } = props.config

  return {
    countries: countries || [],
    language: language || DEFAULT_LANGUAGE,
  }
})

// Methods
function toggleDetails() {
  showDetails.value = !showDetails.value
}

function onSearch(query) {
  // Clear previous timeout
  if (searchTimeout.value) {
    clearTimeout(searchTimeout.value)
  }

  if (!query?.length) {
    options.value = []
    return
  }

  // Debounce the search
  searchTimeout.value = setTimeout(() => {
    performSearch(query)
  }, DEBOUNCE_DELAY)
}

async function performSearch(query) {
  try {
    const response = await performAddressSearch(query)
    options.value = processSearchResults(response.results || [])
  } catch (error) {
    handleSearchError(error)
  }
}

async function performAddressSearch(query) {
  const { countries, language } = searchConfig.value

  const payload = {
    query,
    exclude_fields: props.config.exclude_fields || [],
    countries,
    language: Array.isArray(language) ? language.join(',') : language,
  }

  const response = await Statamic.$axios.post('/cp/simple-address/search', payload)
  return response.data
}

function processSearchResults(data) {
  return data.map((item) => ({
    label: item.label,
    value: item,
  }))
}

function handleSearchError(error) {
  console.error('Address search failed:', error)

  const message = error.response?.data?.message || __('Failed to search addresses. Please try again.')
  Statamic.$toast.error(__(message))
}

async function onCoordinatesChanged({ lat, lon }) {
  try {
    const language = Array.isArray(searchConfig.value.language)
      ? searchConfig.value.language.join(',')
      : searchConfig.value.language

    const response = await Statamic.$axios.post('/cp/simple-address/reverse', {
      lat,
      lon,
      language: language || null,
      exclude_fields: props.config.exclude_fields || [],
    })

    const results = response.data.results || []

    if (results.length > 0) {
      update(results[0])
      Statamic.$toast.success(__('Address updated from map'))
    } else {
      // No address found - keep existing data but update coordinates
      updateCoordinatesOnly(lat, lon)
      Statamic.$toast.info(__('Coordinates updated'))
    }
  } catch (error) {
    console.error('Reverse geocoding failed:', error)
    const message = error.response?.data?.message || __('Failed to lookup address. Please try again.')
    Statamic.$toast.error(__(message))
    // Revert marker on error
    detailsPanel.value?.revertMarkerPosition()
  }
}

function updateCoordinatesOnly(lat, lon) {
  const currentValue = props.value?.value || props.value
  if (currentValue) {
    update({
      ...currentValue,
      lat,
      lon,
    })
  }
}

// Cleanup on component unmount
watch(
  () => searchTimeout.value,
  (_, oldTimeout) => {
    if (oldTimeout) {
      clearTimeout(oldTimeout)
    }
  },
)
</script>

<style scoped>
/* Select wrapper for positioning details button */
.simple-address-select-wrapper {
  position: relative;
}

/* Details button positioned inside the select */
.simple-address-details-btn {
  position: absolute;
  top: 50%;
  right: 32px;
  transform: translateY(-50%);
  z-index: 1;
  padding: 2px 8px 1px 8px;
  font-size: 12px;
  color: rgb(67 169 255);
  background: linear-gradient(90deg, rgba(255, 255, 255, 0) 0%, #fff 15%, #fff 100%);
  border: none;
  cursor: pointer;
  white-space: nowrap;
}

.simple-address-details-btn:hover {
  color: rgb(47 149 235);
  text-decoration: underline;
}

/* Dark mode */
:root.dark .simple-address-details-btn {
  color: rgb(96 165 250);
  background: linear-gradient(90deg, rgba(31, 41, 55, 0) 0%, rgb(31 41 55) 15%, rgb(31 41 55) 100%);
}

:root.dark .simple-address-details-btn:hover {
  color: rgb(147 197 253);
}
</style>
