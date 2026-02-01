<template>
  <div class="space-y-2">
    <Combobox
      v-model="selectedKey"
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
        <div class="flex items-center">
          <svg-icon name="light/location-pin" class="h-4 w-4 flex-shrink-0 text-gray-500 ltr:mr-2 rtl:ml-2" />
          <span v-text="option.label" />
        </div>
      </template>
      <template #no-options="{ searchQuery }">
        <div class="dark:text-dark-150 px-4 py-2 text-sm text-gray-700">
          {{ searchQuery ? __('No addresses found.') : __('Type to search for an address...') }}
        </div>
      </template>
    </Combobox>

    <div v-if="value" class="flex items-center gap-2">
      <button
        type="button"
        class="text-blue dark:text-dark-blue-100 dark:hover:text-dark-blue-150 flex items-center gap-1 text-sm outline-none hover:text-blue-700"
        @click="toggleDetails"
      >
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4">
          <path
            fill-rule="evenodd"
            d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a.75.75 0 000 1.5h.253a.25.25 0 01.244.304l-.459 2.066A1.75 1.75 0 0010.744 15h.505a.75.75 0 000-1.5h-.462a.246.246 0 01-.24-.197l-.46-2.066A1.75 1.75 0 009.253 9H9z"
            clip-rule="evenodd"
          />
        </svg>
        {{ showDetails ? __('Hide details') : __('Show details') }}
      </button>
    </div>

    <AddressDetailsPanel
      v-if="showDetails && value"
      ref="detailsPanel"
      :address="value"
      @coordinates-changed="onCoordinatesChanged"
    />
  </div>
</template>

<script setup>
import { ref, computed, watch, getCurrentInstance, onBeforeUnmount } from 'vue'
import { Fieldtype } from '@statamic/cms'
import { Combobox } from '@statamic/cms/ui'
import AddressDetailsPanel from './components/AddressDetailsPanel.vue'

// Constants
const DEFAULT_LANGUAGE = 'en'
const DEBOUNCE_DELAY = 300

// Get global properties (including $axios, $toast)
const instance = getCurrentInstance()
const globalProperties = instance?.appContext?.config?.globalProperties ?? {}

function getHttpClient() {
  return globalProperties.$axios ?? Statamic?.$app?.config?.globalProperties?.$axios ?? Statamic?.$axios
}

function getToastClient() {
  return globalProperties.$toast ?? Statamic?.$toast
}

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
function getAddressData(address) {
  return address?.value || address
}

function getAddressKey(address) {
  const data = getAddressData(address)
  if (!data) return null

  const label = data.label ?? ''
  const lat = data.lat ?? ''
  const lon = data.lon ?? ''

  return JSON.stringify([label, lat, lon])
}

const normalizedValue = computed(() => getAddressData(props.value) ?? null)

const selectedKey = computed({
  get() {
    return normalizedValue.value ? getAddressKey(normalizedValue.value) : null
  },
  set(newKey) {
    if (!newKey) {
      update(null)
      showDetails.value = false
      return
    }

    const selected = options.value.find((opt) => opt.value === newKey)
    if (!selected) {
      return
    }

    update(selected.address ?? null)
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

function normalizeLanguage(language) {
  return Array.isArray(language) ? language.join(',') : language
}

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
    // Preserve the current value's option when clearing search
    const currentOption = getCurrentValueOption()
    options.value = currentOption ? [currentOption] : []
    return
  }

  // Debounce the search
  searchTimeout.value = setTimeout(() => {
    performSearch(query)
  }, DEBOUNCE_DELAY)
}

function getCurrentValueOption() {
  const data = normalizedValue.value
  if (!data) return null

  const key = getAddressKey(data)
  if (!key) return null

  return { label: data.label, value: key, address: data }
}

async function performSearch(query) {
  try {
    const response = await performAddressSearch(query)
    const searchResults = processSearchResults(response.results || [])

    // Preserve the current value's option so it remains selectable after search
    const currentOption = getCurrentValueOption()
    if (currentOption && !searchResults.some((opt) => opt.value === currentOption.value)) {
      options.value = [currentOption, ...searchResults]
    } else {
      options.value = searchResults
    }
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
    language: normalizeLanguage(language),
  }

  const http = getHttpClient()

  if (!http) {
    throw new Error('No HTTP client available')
  }

  const response = await http.post('/cp/simple-address/search', payload)
  return response.data
}

function processSearchResults(data) {
  return data.map((item) => ({
    label: item.label,
    value: getAddressKey(item),
    address: item,
  }))
}

function handleSearchError(error) {
  console.error('Address search failed:', error)

  const message = error.response?.data?.message || __('Failed to search addresses. Please try again.')
  getToastClient()?.error(__(message))
}

async function onCoordinatesChanged({ lat, lon }) {
  try {
    const language = normalizeLanguage(searchConfig.value.language)

    const http = getHttpClient()

    if (!http) {
      throw new Error('No HTTP client available')
    }

    const response = await http.post('/cp/simple-address/reverse', {
      lat,
      lon,
      language: language || null,
      exclude_fields: props.config.exclude_fields || [],
    })

    const results = response.data.results || []

    if (results.length > 0) {
      update(results[0])
      getToastClient()?.success(__('Address updated from map'))
    } else {
      // No address found - keep existing data but update coordinates
      updateCoordinatesOnly(lat, lon)
      getToastClient()?.info(__('Coordinates updated'))
    }
  } catch (error) {
    console.error('Reverse geocoding failed:', error)
    const message = error.response?.data?.message || __('Failed to lookup address. Please try again.')
    getToastClient()?.error(__(message))
    // Revert marker on error
    detailsPanel.value?.revertMarkerPosition()
  }
}

function updateCoordinatesOnly(lat, lon) {
  const currentValue = normalizedValue.value
  if (currentValue) {
    update({
      ...currentValue,
      lat,
      lon,
    })
  }
}

onBeforeUnmount(() => {
  if (searchTimeout.value) {
    clearTimeout(searchTimeout.value)
  }
})

// Ensure the current value is always selectable/displayable.
watch(
  () => props.value,
  (newValue) => {
    const data = getAddressData(newValue)
    if (!data) return

    const key = getAddressKey(data)
    if (!key) return

    if (!options.value.some((opt) => opt.value === key)) {
      options.value = [{ label: data.label, value: key, address: data }, ...options.value]
    }
  },
  { immediate: true, deep: true },
)
</script>
