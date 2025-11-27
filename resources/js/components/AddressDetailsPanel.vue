<template>
  <div class="space-y-4">
    <!-- Map Container -->
    <div class="flex flex-col overflow-hidden rounded" style="gap: 4px">
      <div class="relative">
        <div ref="mapContainer" class="w-full border bg-gray-100" style="aspect-ratio: 16 / 9" />
        <!-- Coordinates overlay -->
        <div
          v-if="mouseCoords"
          class="pointer-events-none absolute font-mono text-gray-700"
          style="
            top: 8px;
            right: 8px;
            z-index: 1000;
            font-size: 11px;
            background: rgba(255, 255, 255, 0.9);
            padding: 4px 8px;
            border-radius: 4px;
          "
        >
          {{ formatCoord(mouseCoords.lat, 'lat') }}, {{ formatCoord(mouseCoords.lng, 'lng') }}
        </div>
      </div>

      <!-- Location Data -->
      <pre
        class="overflow-auto rounded border border-gray-200 bg-white p-4 text-gray-700"
        style="max-height: 200px; font-size: 10px"
        >{{ JSON.stringify(address, null, 2) }}
        </pre
      >
    </div>
  </div>
</template>

<script>
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'

export default {
  name: 'AddressDetailsPanel',

  props: {
    address: {
      type: Object,
      required: true,
    },
  },

  data() {
    return {
      map: null,
      marker: null,
      originalPosition: null,
      dragEndTimeout: null,
      mouseCoords: null,
    }
  },

  watch: {
    address: {
      handler() {
        this.$nextTick(() => {
          this.initializeMap()
        })
      },
      deep: true,
    },
  },

  mounted() {
    this.$nextTick(() => {
      this.initializeMap()
    })
  },

  beforeDestroy() {
    if (this.dragEndTimeout) {
      clearTimeout(this.dragEndTimeout)
    }
    if (this.marker) {
      this.marker.off('dragend')
    }
    if (this.map) {
      this.map.off('mousemove')
      this.map.off('mouseout')
      this.map.remove()
    }
  },

  methods: {
    initializeMap() {
      const addressData = this.address.value || this.address
      const { lat, lon } = addressData

      if (!lat || !lon) {
        return
      }

      // Remove existing map instance
      if (this.map) {
        this.map.remove()
      }

      const mapContainer = this.$refs.mapContainer

      if (!mapContainer) {
        return
      }

      // Initialize map
      this.map = L.map(mapContainer).setView([parseFloat(lat), parseFloat(lon)], 13)

      // Add CartoDB Positron tiles (light grey style)
      L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', {
        attribution:
          '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors &copy; <a href="https://carto.com/attributions">CARTO</a>',
        subdomains: 'abcd',
        maxZoom: 20,
      }).addTo(this.map)

      // Add draggable marker with custom styling
      const latNum = parseFloat(lat)
      const lonNum = parseFloat(lon)

      this.marker = L.marker([latNum, lonNum], {
        draggable: true,
        icon: L.divIcon({
          className: 'simple-address-marker',
          html: `<div class="marker-pin"><div class="marker-inner"></div></div>`,
          iconSize: [36, 36],
          iconAnchor: [18, 18],
        }),
      }).addTo(this.map)

      // Store original position for reverting on error
      this.originalPosition = [latNum, lonNum]

      // Add drag event listener
      this.marker.on('dragend', () => this.onMarkerDragEnd())

      // Track mouse coordinates
      this.map.on('mousemove', (e) => {
        this.mouseCoords = {
          lat: e.latlng.lat.toFixed(5),
          lng: e.latlng.lng.toFixed(5),
        }
      })
      this.map.on('mouseout', () => {
        this.mouseCoords = null
      })

      // Invalidate size to ensure proper rendering
      this.map.invalidateSize()
    },

    onMarkerDragEnd() {
      // Cancel previous pending drag
      if (this.dragEndTimeout) {
        clearTimeout(this.dragEndTimeout)
      }

      // Debounce: wait 500ms after drag stops before emitting
      this.dragEndTimeout = setTimeout(() => {
        const newLatLng = this.marker.getLatLng()
        this.$emit('coordinates-changed', {
          lat: parseFloat(newLatLng.lat).toFixed(7),
          lon: parseFloat(newLatLng.lng).toFixed(7),
        })
      }, 500)
    },

    revertMarkerPosition() {
      if (this.marker && this.originalPosition) {
        this.marker.setLatLng(this.originalPosition)
      }
    },

    formatCoord(value, type) {
      const num = parseFloat(value)
      const dir = type === 'lat' ? (num >= 0 ? 'N' : 'S') : num >= 0 ? 'E' : 'W'
      return `${Math.abs(num).toFixed(4)}° ${dir}`
    },
  },
}
</script>

<style scoped>
pre {
  font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
  line-height: 1.5;
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
