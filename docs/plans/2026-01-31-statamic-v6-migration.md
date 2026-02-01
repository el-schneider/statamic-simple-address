# Statamic v6 Migration Plan

> **For Claude:** Implement this plan task-by-task. Manual testing only - no automated tests in this phase.

**Goal:** Migrate the Simple Address fieldtype from Statamic v5 (Vue 2) to Statamic v6 (Vue 3), using the Composition API and Statamic's built-in UI components.

**Architecture:** Replace vue-select with Statamic's Combobox component, convert Vue components to Composition API with `<script setup>`, update Vite configuration to use the Statamic CMS plugin, and add dark mode support for custom elements.

**Tech Stack:** Vue 3, Statamic v6, Composition API, `@statamic/cms/ui` Combobox, Leaflet.js

---

## Overview of Changes

| Area             | Current (v5)                  | Target (v6)                            |
| ---------------- | ----------------------------- | -------------------------------------- |
| Vue Version      | Vue 2.7                       | Vue 3.4+                               |
| Component Style  | Options API + Fieldtype mixin | Composition API + Fieldtype composable |
| Select Component | vue-select                    | `@statamic/cms/ui` Combobox            |
| Vite Plugin      | `@vitejs/plugin-vue2`         | `@statamic/cms/vite-plugin`            |
| Lifecycle Hooks  | `beforeDestroy`               | `beforeUnmount`                        |
| Dark Mode        | None                          | Supported                              |

---

## Task 1: Update Build Configuration

**Files:**

- Modify: `package.json`
- Modify: `vite.config.js`

### Step 1: Update package.json dependencies

Replace the devDependencies and dependencies sections:

```json
{
  "devDependencies": {
    "@statamic/cms": "file:./vendor/statamic/cms/resources/dist-package",
    "eslint": "^9.28.0",
    "eslint-config-prettier": "^10.1.8",
    "eslint-plugin-vue": "^10.5.1",
    "globals": "^16.5.0",
    "husky": "^9.1.7",
    "laravel-vite-plugin": "^2.0.1",
    "prettier": "^3.5.3",
    "prettier-plugin-tailwindcss": "^0.7.1",
    "vite": "^7.2.2",
    "vue-eslint-parser": "^10.2.0"
  },
  "dependencies": {
    "leaflet": "^1.9.4"
  }
}
```

**Key changes:**

- Remove `@vitejs/plugin-vue2`
- Remove `vue` (provided by Statamic)
- Remove `vue-select` (replaced by Combobox)
- Add `@statamic/cms` pointing to vendor dist-package

### Step 2: Update vite.config.js

```js
import statamic from '@statamic/cms/vite-plugin'
import laravel from 'laravel-vite-plugin'
import { defineConfig } from 'vite'

export default defineConfig({
  plugins: [
    laravel({
      input: ['resources/js/simple-address.js'],
      publicDirectory: 'resources/dist',
    }),
    statamic(),
  ],
})
```

### Step 3: Reinstall dependencies in test app

Run in the test app directory (`../statamic-simple-address-v6`):

```bash
cd ../statamic-simple-address-v6 && composer update && npm install
```

Then back in addon directory:

```bash
npm install
```

### Step 4: Verify build works

```bash
npm run build
```

Expected: Build completes without errors (components will have Vue 3 incompatibilities at this point, which is fine).

### Step 5: Commit

```bash
git add package.json vite.config.js
git commit -m "build: update to Statamic v6 vite configuration"
```

---

## Task 2: Migrate AddressDetailsPanel to Vue 3 Composition API

**Files:**

- Modify: `resources/js/components/AddressDetailsPanel.vue`

This component is simpler and has no Statamic-specific dependencies, making it a good starting point.

### Step 1: Rewrite component with Composition API

Replace entire file content:

```vue
<template>
  <div class="space-y-4">
    <!-- Map Container -->
    <div class="mt-1 flex flex-col gap-1 overflow-hidden rounded">
      <div class="relative">
        <div ref="mapContainer" class="simple-address-map w-full border" style="aspect-ratio: 16 / 9" />
        <!-- Coordinates overlay -->
        <div
          v-if="mouseCoords"
          class="simple-address-coords pointer-events-none absolute font-mono"
          style="top: 8px; right: 8px; z-index: 1000; font-size: 11px; padding: 4px 8px; border-radius: 4px"
        >
          {{ formatCoord(mouseCoords.lat, 'lat') }}, {{ formatCoord(mouseCoords.lng, 'lng') }}
        </div>
      </div>

      <!-- Location Data -->
      <pre
        class="simple-address-yaml overflow-auto rounded border p-4"
        style="max-height: 200px; font-size: 10px"
        v-html="formattedYaml"
      />
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, onBeforeUnmount, nextTick } from 'vue'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'
import { formatAsYaml } from '../utils/yamlFormatter'

const props = defineProps({
  address: {
    type: Object,
    required: true,
  },
})

const emit = defineEmits(['coordinates-changed'])

// Refs
const mapContainer = ref(null)
const map = ref(null)
const marker = ref(null)
const originalPosition = ref(null)
const dragEndTimeout = ref(null)
const mouseCoords = ref(null)

// Computed
const formattedYaml = computed(() => formatAsYaml(props.address))

// Methods
function initializeMap() {
  const addressData = props.address.value || props.address
  const { lat, lon } = addressData

  if (!lat || !lon) {
    return
  }

  // Remove existing map instance
  if (map.value) {
    map.value.remove()
  }

  if (!mapContainer.value) {
    return
  }

  // Initialize map
  map.value = L.map(mapContainer.value).setView([parseFloat(lat), parseFloat(lon)], 13)

  // Add CartoDB Positron tiles (light grey style)
  L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', {
    attribution:
      '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors &copy; <a href="https://carto.com/attributions">CARTO</a>',
    subdomains: 'abcd',
    maxZoom: 20,
  }).addTo(map.value)

  // Add draggable marker with custom styling
  const latNum = parseFloat(lat)
  const lonNum = parseFloat(lon)

  marker.value = L.marker([latNum, lonNum], {
    draggable: true,
    icon: L.divIcon({
      className: 'simple-address-marker',
      html: `<div class="marker-pin"><div class="marker-inner"></div></div>`,
      iconSize: [36, 36],
      iconAnchor: [18, 18],
    }),
  }).addTo(map.value)

  // Store original position for reverting on error
  originalPosition.value = [latNum, lonNum]

  // Add drag event listener
  marker.value.on('dragend', onMarkerDragEnd)

  // Track mouse coordinates
  map.value.on('mousemove', (e) => {
    mouseCoords.value = {
      lat: e.latlng.lat.toFixed(5),
      lng: e.latlng.lng.toFixed(5),
    }
  })
  map.value.on('mouseout', () => {
    mouseCoords.value = null
  })

  // Invalidate size to ensure proper rendering
  map.value.invalidateSize()
}

function onMarkerDragEnd() {
  // Cancel previous pending drag
  if (dragEndTimeout.value) {
    clearTimeout(dragEndTimeout.value)
  }

  // Debounce: wait 500ms after drag stops before emitting
  dragEndTimeout.value = setTimeout(() => {
    const newLatLng = marker.value.getLatLng()
    emit('coordinates-changed', {
      lat: parseFloat(newLatLng.lat).toFixed(7),
      lon: parseFloat(newLatLng.lng).toFixed(7),
    })
  }, 500)
}

function revertMarkerPosition() {
  if (marker.value && originalPosition.value) {
    marker.value.setLatLng(originalPosition.value)
  }
}

function formatCoord(value, type) {
  const num = parseFloat(value)
  const dir = type === 'lat' ? (num >= 0 ? 'N' : 'S') : num >= 0 ? 'E' : 'W'
  return `${Math.abs(num).toFixed(4)}° ${dir}`
}

// Expose methods for parent component
defineExpose({
  revertMarkerPosition,
})

// Watchers
watch(
  () => props.address,
  () => {
    nextTick(() => {
      initializeMap()
    })
  },
  { deep: true },
)

// Lifecycle
onMounted(() => {
  nextTick(() => {
    initializeMap()
  })
})

onBeforeUnmount(() => {
  if (dragEndTimeout.value) {
    clearTimeout(dragEndTimeout.value)
  }
  if (marker.value) {
    marker.value.off('dragend')
  }
  if (map.value) {
    map.value.off('mousemove')
    map.value.off('mouseout')
    map.value.remove()
  }
})
</script>

<style scoped>
pre {
  font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
  line-height: 1.5;
}

/* Light mode styles */
.simple-address-map {
  background-color: #f3f4f6;
}

.simple-address-coords {
  background: rgba(255, 255, 255, 0.9);
  color: #374151;
}

.simple-address-yaml {
  background-color: white;
  border-color: #e5e7eb;
  color: #374151;
}

.simple-address-yaml :deep(.yaml-value) {
  color: rgb(67 169 255);
}

/* Dark mode styles */
:root.dark .simple-address-map {
  background-color: #1f2937;
}

:root.dark .simple-address-coords {
  background: rgba(31, 41, 55, 0.9);
  color: #d1d5db;
}

:root.dark .simple-address-yaml {
  background-color: #1f2937;
  border-color: #374151;
  color: #d1d5db;
}

:root.dark .simple-address-yaml :deep(.yaml-value) {
  color: rgb(96 165 250);
}
</style>

<style>
/* Marker styles (unscoped for Leaflet DOM elements) */
.simple-address-marker {
  background: transparent !important;
  border: none !important;
}

.simple-address-marker .marker-pin {
  width: 36px;
  height: 36px;
  background: #ef4444;
  border: 3px solid #fff;
  border-radius: 50%;
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.2);
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: grab;
  transition:
    transform 0.15s ease,
    box-shadow 0.15s ease;
  box-sizing: border-box;
}

.simple-address-marker .marker-inner {
  width: 9px;
  height: 9px;
  background: #fff;
  border-radius: 50%;
}

.simple-address-marker .marker-pin:hover {
  transform: scale(1.1);
  cursor: grab;
  box-shadow: 0 2px 8px rgba(239, 68, 68, 0.4);
}

.simple-address-marker .marker-pin:active {
  cursor: grabbing;
}

.leaflet-dragging .simple-address-marker .marker-pin {
  cursor: grabbing;
  transform: scale(1.15);
  box-shadow: 0 3px 10px rgba(239, 68, 68, 0.4);
}
</style>
```

**Key changes:**

- `<script setup>` with Composition API
- `beforeDestroy` → `onBeforeUnmount`
- `this.$refs` → template refs with `ref()`
- `this.$emit` → `emit()` from `defineEmits`
- `this.$nextTick` → imported `nextTick`
- Added dark mode CSS using `:root.dark` selector
- `defineExpose` for parent access to `revertMarkerPosition`

### Step 2: Commit

```bash
git add resources/js/components/AddressDetailsPanel.vue
git commit -m "refactor: migrate AddressDetailsPanel to Vue 3 Composition API"
```

---

## Task 3: Migrate Main AddressField to Vue 3 with Combobox

**Files:**

- Modify: `resources/js/address-field.vue`

### Step 1: Rewrite component with Composition API and Combobox

Replace entire file content:

```vue
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
const isSearching = ref(false)

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
  isSearching.value = true

  try {
    const response = await performAddressSearch(query)
    options.value = processSearchResults(response.results || [])
  } catch (error) {
    handleSearchError(error)
  } finally {
    isSearching.value = false
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

// Cleanup on unmount
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
```

**Key changes:**

- Replaced `vue-select` with Statamic's `Combobox` component
- Using `Fieldtype.use(emit, props)` composable pattern
- `ignoreFilter` prop tells Combobox we handle filtering ourselves
- Custom debouncing for search (Combobox doesn't have loading callback)
- `this.__()` → `__()` (global function)
- `this.$toast` → `Statamic.$toast`
- Dark mode support for details button

### Step 2: Commit

```bash
git add resources/js/address-field.vue
git commit -m "refactor: migrate AddressField to Vue 3 with Statamic Combobox"
```

---

## Task 4: Update Entry Point and ESLint Configuration

**Files:**

- Modify: `resources/js/simple-address.js`
- Modify: `eslint.config.js` (if needed)

### Step 1: Update entry point (minimal changes needed)

The entry point should remain largely the same:

```js
import AddressField from './address-field.vue'

Statamic.booting(() => {
  Statamic.$components.register('simple_address-fieldtype', AddressField)
})
```

No changes needed - the registration API remains the same.

### Step 2: Update ESLint config for Vue 3

Check if `eslint.config.js` needs updates for Vue 3. The `eslint-plugin-vue` should be configured for Vue 3:

```js
// Ensure vue3-recommended is used instead of vue2
import pluginVue from 'eslint-plugin-vue'

// Use vue3 rules
...pluginVue.configs['flat/recommended'], // or 'flat/vue3-recommended'
```

### Step 3: Commit (if changes were needed)

```bash
git add eslint.config.js
git commit -m "build: update ESLint config for Vue 3"
```

---

## Task 5: Build and Fix Any Remaining Issues

### Step 1: Run the build

```bash
npm run build
```

### Step 2: Fix any build errors

Common issues to watch for:

- Import paths
- Missing exports
- TypeScript declaration issues (if any)

### Step 3: Run linting

```bash
npm run check
```

Fix any linting issues that arise.

### Step 4: Commit fixes

```bash
git add -A
git commit -m "fix: resolve build and lint issues for Vue 3"
```

---

## Task 6: Manual Testing in Test App

**Test App:** `http://statamic-simple-address-v6.test`
**Credentials:** `agent@agent.md` / `agent`
**Test Page:** `http://statamic-simple-address-v6.test/cp/collections/pages/entries/4e8a2abf-764e-4001-b57f-2de19029f358`

### Step 1: Start dev server in addon directory

```bash
npm run dev
```

### Step 2: Test Basic Functionality

Using agent-browser, verify:

1. **Page loads without errors**
   - Navigate to the test page
   - Check browser console for JavaScript errors
   - Verify the Simple Address field renders

2. **Search functionality**
   - Type an address (e.g., "Berlin, Germany")
   - Verify dropdown shows results after typing
   - Verify debouncing works (no request per keystroke)

3. **Selection**
   - Select an address from the dropdown
   - Verify the field value updates
   - Verify the "details" button appears

4. **Details panel**
   - Click "details" button
   - Verify map renders with marker at correct position
   - Verify YAML preview shows address data
   - Verify mouse coordinates overlay works

5. **Map marker dragging**
   - Drag the marker to a new position
   - Verify reverse geocoding triggers
   - Verify address updates (or coordinates if no address found)
   - Verify toast notification appears

6. **Clear/deselect**
   - Clear the selected address
   - Verify field resets
   - Verify details panel closes

7. **Save functionality**
   - Make a selection
   - Save the entry
   - Reload the page
   - Verify the address persists

### Step 3: Test Dark Mode (if applicable)

- Toggle dark mode in Statamic CP preferences
- Verify map container, YAML preview, and details button have appropriate dark styling

### Step 4: Test with Different Geocoding Providers

The test app should have these API keys configured:

- `GEOAPIFY_API_KEY`
- `GEOCODIFY_API_KEY`
- `GOOGLE_GEOCODE_API_KEY`
- `MAPBOX_ACCESS_TOKEN`

Test at least one alternative provider if time permits.

### Step 5: Document any issues found

Create a list of any bugs or issues discovered during manual testing for follow-up.

---

## Task 7: Final Cleanup and Commit

### Step 1: Format all files

```bash
npm run fix
```

### Step 2: Final commit

```bash
git add -A
git commit -m "feat: complete Statamic v6 migration"
```

---

## Verification Checklist

Before considering the migration complete:

- [ ] `npm run build` succeeds
- [ ] `npm run check` passes (lint + format)
- [ ] Field renders in Statamic CP
- [ ] Address search works
- [ ] Address selection works
- [ ] Details panel shows map and YAML
- [ ] Map marker is draggable
- [ ] Reverse geocoding works
- [ ] Field value saves and persists
- [ ] No console errors
- [ ] Dark mode styling works (if CP has dark mode enabled)

---

## Rollback Plan

If critical issues are found:

1. The original Vue 2 code should still be in git history
2. Revert to previous commit: `git revert HEAD`
3. Or create a new branch from the last working commit

---

## Post-Migration Tasks (Future)

These are out of scope for the initial migration but should be addressed later:

1. Add automated tests for Vue components
2. Update README with v6 compatibility notes
3. Consider publishing to Packagist/marketplace
4. Performance optimization if needed
