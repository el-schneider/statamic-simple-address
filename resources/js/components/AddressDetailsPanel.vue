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
