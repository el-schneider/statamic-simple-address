<template>
  <div class="space-y-4">
    <!-- Map Container -->
    <div class="flex flex-col gap-1 overflow-hidden rounded">
      <div ref="mapContainer" class="w-full border bg-gray-100" style="aspect-ratio: 16 / 9" />

      <!-- Location Data -->
      <pre
        style="max-height: 200px"
        class="text-2xs overflow-auto rounded border border-gray-200 bg-white p-4 text-gray-700"
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

      // Add OpenStreetMap tiles
      L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors',
        maxZoom: 19,
      }).addTo(this.map)

      // Add draggable marker with custom styling
      this.marker = L.marker([parseFloat(lat), parseFloat(lon)], {
        draggable: true,
        icon: L.divIcon({
          className: 'simple-address-marker',
          html: '<div class="w-4 h-4 rounded-full bg-red-600 border-2 border-red-700 shadow-lg"></div>',
          iconSize: [16, 16],
          iconAnchor: [8, 8],
        }),
      }).addTo(this.map)

      // Store original position for reverting on error
      this.originalPosition = [parseFloat(lat), parseFloat(lon)]

      // Add drag event listener
      this.marker.on('dragend', () => this.onMarkerDragEnd())

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
  },
}
</script>

<style scoped>
pre {
  font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
  line-height: 1.5;
}

:deep(.simple-address-marker) {
  background: transparent;
}
</style>
