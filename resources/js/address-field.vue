<template>
  <div class="simple-address-field space-y-2">
    <Combobox
      v-model="selectedKey"
      :options="options"
      :placeholder="config.placeholder || __('Search for an address...')"
      searchable
      ignore-filter
      clearable
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
        @click="showDetails = !showDetails"
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
import { ref, computed, watch, getCurrentInstance } from 'vue'
import { Fieldtype } from '@statamic/cms'
import { Combobox } from '@statamic/cms/ui'
import AddressDetailsPanel from './components/AddressDetailsPanel.vue'

// Fieldtype setup
const emit = defineEmits(Fieldtype.emits)
const props = defineProps(Fieldtype.props)
const { expose, update } = Fieldtype.use(emit, props)
defineExpose(expose)

// Global properties
const { $axios, $toast } = getCurrentInstance().appContext.config.globalProperties

// State
const options = ref([])
const showDetails = ref(false)
const detailsPanel = ref(null)

// Debounce helper
let searchTimeout = null
function debounce(fn, delay) {
  return (...args) => {
    clearTimeout(searchTimeout)
    searchTimeout = setTimeout(() => fn(...args), delay)
  }
}

// Create address key for Combobox value tracking
function addressKey(address) {
  if (!address) return null
  return JSON.stringify([address.label ?? '', address.lat ?? '', address.lon ?? ''])
}

// Current value as Combobox option
function currentValueOption() {
  if (!props.value) return null
  const key = addressKey(props.value)
  return { label: props.value.label, value: key, address: props.value }
}

// Computed
const selectedKey = computed({
  get: () => (props.value ? addressKey(props.value) : null),
  set: (key) => {
    if (!key) {
      update(null)
      showDetails.value = false
      return
    }
    const selected = options.value.find((opt) => opt.value === key)
    if (selected) {
      update(selected.address)
      showDetails.value = false
    }
  },
})

// Search
const performSearch = debounce(async (query, loading) => {
  try {
    const response = await $axios.post('/cp/simple-address/search', {
      query,
      exclude_fields: props.config.exclude_fields || [],
      countries: props.config.countries || [],
      language: props.config.language || 'en',
    })

    const results = (response.data.results || []).map((item) => ({
      label: item.label,
      value: addressKey(item),
      address: item,
    }))

    // Keep current value in options so it stays selectable
    const current = currentValueOption()
    options.value = current && !results.some((r) => r.value === current.value) ? [current, ...results] : results
  } catch (error) {
    console.error('Address search failed:', error)
    $toast?.error(error.response?.data?.message || __('Failed to search addresses.'))
  } finally {
    loading(false)
  }
}, 300)

function onSearch(query, loading) {
  if (!query) {
    const current = currentValueOption()
    options.value = current ? [current] : []
    return
  }
  loading(true)
  performSearch(query, loading)
}

// Reverse geocoding when map marker is dragged
async function onCoordinatesChanged({ lat, lon }) {
  try {
    const response = await $axios.post('/cp/simple-address/reverse', {
      lat,
      lon,
      language: props.config.language || 'en',
      exclude_fields: props.config.exclude_fields || [],
    })

    const results = response.data.results || []
    if (results.length > 0) {
      update(results[0])
      $toast?.success(__('Address updated from map'))
    } else {
      update({ ...props.value, lat, lon })
      $toast?.info(__('Coordinates updated'))
    }
  } catch (error) {
    console.error('Reverse geocoding failed:', error)
    $toast?.error(error.response?.data?.message || __('Failed to lookup address.'))
    detailsPanel.value?.revertMarkerPosition()
  }
}

// Ensure current value is always in options
watch(
  () => props.value,
  (newValue) => {
    if (!newValue) return
    const key = addressKey(newValue)
    if (!options.value.some((opt) => opt.value === key)) {
      options.value = [{ label: newValue.label, value: key, address: newValue }, ...options.value]
    }
  },
  { immediate: true },
)
</script>
