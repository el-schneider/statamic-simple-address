# Changelog

## v0.2.4 - 2026-09-22

### What's fixed

- Replace the default CARTO tiles with OpenStreetMap to avoid API key watermarks.

### What's new

- Configure provider-neutral light and dark tile layers through `config/simple-address.php`.
- Reuse the light layer in dark mode when no separate dark layer is configured.
- Existing published configs remain compatible.

**Full Changelog**: https://github.com/el-schneider/statamic-simple-address/compare/v0.2.3...v0.2.4

## v0.2.1 - 2025-12-03

### Whats'fixed

- Add mergeConfigFrom and clean up ServiceProvider

## v0.2.0 - 2025-12-01

### What's new

- Configurable geocoding provider system with support for multiple providers (Nominatim, Google Maps, Mapbox, Geoapify)
- Interactive map with draggable markers and reverse geocoding
- Backend routing and caching for rate limit compliance
- Comprehensive test coverage with Pest
- Updated frontend with Leaflet integration

## v0.1.0 - 2025-05-31

### What's new

- v5 support

**Full Changelog**: https://github.com/el-schneider/statamic-simple-address/commits/v0.1.0
