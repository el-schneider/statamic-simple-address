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

      // Add marker
      L.circleMarker([parseFloat(lat), parseFloat(lon)], {
        radius: 8,
        fillColor: '#ef4444',
        color: '#dc2626',
        weight: 2,
        opacity: 1,
        fillOpacity: 1,
      }).addTo(this.map)

      // Invalidate size to ensure proper rendering
      this.map.invalidateSize()
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
