<template>
  <ui-card inset flat class="overflow-hidden">
    <div class="relative">
      <div ref="mapContainer" class="aspect-video" />
      <div v-if="mouseCoords" class="pointer-events-none absolute top-3 right-3 z-10 font-mono">
        <ui-badge> {{ formatCoord(mouseCoords.lat, 'lat') }}, {{ formatCoord(mouseCoords.lng, 'lng') }} </ui-badge>
      </div>
    </div>
    <pre class="max-h-48 overflow-auto p-4 text-xs sm:p-4.5 [&>span]:text-gray-500" v-html="formattedYaml" />
  </ui-card>
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
  // null, not 13: an unset Statamic integer arrives as null, so the fallback
  // has to live in zoomLevel anyway. Keeping it in one place.
  zoom: {
    type: [Number, String],
    default: null,
  },
})

const emit = defineEmits(['coordinates-changed'])

const mapContainer = ref(null)
const map = ref(null)
const marker = ref(null)
const originalPosition = ref(null)
const mouseCoords = ref(null)

// Set from the moment the user drags the marker until the resulting address
// update arrives. It lets syncToAddress tell a marker refinement (keep the
// current view) apart from a freshly selected place (recenter at the zoom).
const awaitingDragEcho = ref(false)

// parseFloat, not Number: Number(null) is 0 and zoom 0 is a valid Leaflet level.
const zoomLevel = computed(() => {
  const zoom = parseFloat(props.zoom)

  return Number.isNaN(zoom) ? 13 : zoom
})

const formattedYaml = computed(() => formatAsYaml(props.address))
const colorMode = computed(() => Statamic.$colorMode.mode.value)

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

  map.value = L.map(mapContainer.value).setView([parseFloat(lat), parseFloat(lon)], zoomLevel.value)

  map.value.zoomControl.remove()
  L.control.zoom({ position: 'bottomright' }).addTo(map.value)

  createTileLayer(map.value)

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

// Keep the map in step with the address without rebuilding it. A marker the user
// dragged stays within the current view, so its zoom and center are left alone;
// only an address that lands off-screen (a fresh search) recenters at the
// configured zoom.
function syncToAddress() {
  const { lat, lon } = props.address

  if (!lat || !lon) {
    return
  }

  if (!map.value) {
    initializeMap()
    return
  }

  const target = [parseFloat(lat), parseFloat(lon)]

  marker.value.setLatLng(target)
  originalPosition.value = target

  // A dragged marker: the user is already looking at the right spot, so keep
  // their zoom and center. Any other change is a newly selected place, which
  // recenters at the configured zoom.
  if (awaitingDragEcho.value) {
    awaitingDragEcho.value = false

    return
  }

  map.value.setView(target, zoomLevel.value)
}

function onMarkerDragEnd() {
  awaitingDragEcho.value = true

  const newLatLng = marker.value.getLatLng()
  emit('coordinates-changed', {
    lat: parseFloat(newLatLng.lat).toFixed(7),
    lon: parseFloat(newLatLng.lng).toFixed(7),
  })
}

function revertMarkerPosition() {
  // The reverse lookup failed, so no address update will follow; clear the flag
  // so the next real selection still recenters.
  awaitingDragEcho.value = false

  if (marker.value && originalPosition.value) {
    marker.value.setLatLng(originalPosition.value)
  }
}

function formatCoord(value, type) {
  const num = parseFloat(value)
  const dir = type === 'lat' ? (num >= 0 ? 'N' : 'S') : num >= 0 ? 'E' : 'W'
  return `${Math.abs(num).toFixed(4)}° ${dir}`
}

function clearTileLayers(map) {
  map?.eachLayer((layer) => {
    if (layer instanceof L.TileLayer) layer.remove()
  })
}

function createTileLayer(map) {
  const style = colorMode.value === 'dark' ? 'dark_all' : 'light_all'

  L.tileLayer(`https://{s}.basemaps.cartocdn.com/${style}/{z}/{x}/{y}{r}.png`, {
    attribution:
      '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors &copy; <a href="https://carto.com/attributions">CARTO</a>',
    subdomains: 'abcd',
    maxZoom: 20,
  }).addTo(map)
}

defineExpose({
  revertMarkerPosition,
})

watch(() => props.address, syncToAddress, { deep: true })

watch(
  () => colorMode.value,
  () => {
    if (map.value) {
      clearTileLayers(map.value)
      createTileLayer(map.value)
    }
  },
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
