<template>
  <div class="space-y-2">
    <div class="dark:border-dark-200 relative overflow-hidden rounded border border-gray-300">
      <div ref="mapContainer" class="dark:bg-dark-100 w-full bg-gray-100" style="aspect-ratio: 16 / 9" />
      <div
        v-if="mouseCoords"
        class="text-2xs dark:bg-dark-100/90 dark:text-dark-150 pointer-events-none absolute top-2 right-2 z-10 rounded bg-white/90 px-2 py-1 font-mono text-gray-700"
      >
        {{ formatCoord(mouseCoords.lat, 'lat') }}, {{ formatCoord(mouseCoords.lng, 'lng') }}
      </div>
    </div>

  </div>
    <pre class="max-h-48 overflow-auto p-4 text-xs sm:p-4.5 [&>span]:text-gray-500" v-html="formattedYaml" />
</template>

<script setup>
import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue'
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

const mapContainer = ref(null)
const map = ref(null)
const marker = ref(null)
const originalPosition = ref(null)
const mouseCoords = ref(null)

const formattedYaml = computed(() => formatAsYaml(props.address))
const colorMode = computed(() => Statamic.$colorMode.mode.value)
const mapStyle = computed(() => (colorMode.value === 'dark' ? 'dark_all' : 'light_all'))

function initializeMap() {
  const { lat, lon } = props.address

  if (!lat || !lon) {
    return
  }

  if (map.value) {
    map.value.remove()
  }

  if (!mapContainer.value) {
    return
  }

  map.value = L.map(mapContainer.value).setView([parseFloat(lat), parseFloat(lon)], 13)

  L.tileLayer(`https://{s}.basemaps.cartocdn.com/${mapStyle.value}/{z}/{x}/{y}{r}.png`, {
    attribution:
      '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors &copy; <a href="https://carto.com/attributions">CARTO</a>',
    subdomains: 'abcd',
    maxZoom: 20,
  }).addTo(map.value)

  map.value.zoomControl.remove()
  L.control.zoom({ position: 'bottomright' }).addTo(map.value)

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

  originalPosition.value = [latNum, lonNum]
  marker.value.on('dragend', onMarkerDragEnd)

  map.value.on('mousemove', (e) => {
    mouseCoords.value = {
      lat: e.latlng.lat.toFixed(5),
      lng: e.latlng.lng.toFixed(5),
    }
  })
  map.value.on('mouseout', () => {
    mouseCoords.value = null
  })

  map.value.invalidateSize()
}

function onMarkerDragEnd() {
  const newLatLng = marker.value.getLatLng()
  emit('coordinates-changed', {
    lat: parseFloat(newLatLng.lat).toFixed(7),
    lon: parseFloat(newLatLng.lng).toFixed(7),
  })
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

defineExpose({
  revertMarkerPosition,
})

watch(
  () => props.address,
  () => initializeMap(),
  { deep: true },
)

watch(
  () => colorMode.value,
  () => initializeMap(),
)

onMounted(() => {
  initializeMap()
})

onBeforeUnmount(() => {
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

<style>
/* Reset Leaflet's z-indexes to reasonable values that don't conflict with Tailwind */
.leaflet-pane {
  z-index: auto;
}

.leaflet-tile-pane {
  z-index: 1;
}

.leaflet-overlay-pane {
  z-index: 2;
}

.leaflet-shadow-pane {
  z-index: 3;
}

.leaflet-marker-pane {
  z-index: 4;
}

.leaflet-tooltip-pane {
  z-index: 5;
}

.leaflet-popup-pane {
  z-index: 6;
}

.leaflet-control {
  z-index: 7;
}

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
