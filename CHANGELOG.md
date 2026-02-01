# Changelog

## v2.0.0 - 2026-02-01

### What's new

- **Statamic v6 support** - Full compatibility with Statamic v6 and its Vue 3-based Control Panel
- **Vue 3 migration** - Complete rewrite using Composition API with `<script setup>`
- **Statamic Combobox** - Replaced vue-select with native Statamic Combobox component
- **Dark mode support** - Proper styling for Statamic's dark mode
- **Browser tests** - Added Pest browser coverage for CP fieldtype

### What's changed

- Aligned fieldtype styling with Statamic v6 CP design system
- Replaced custom CSS with Tailwind utility classes
- Updated build system to use Statamic Vite plugin
- Updated CI to PHP 8.4 and Node 22

### Breaking changes

- **Requires Statamic v6** - This version is not compatible with Statamic v5 or earlier. Use v0.2.x for Statamic v5.

**Full Changelog**: https://github.com/el-schneider/statamic-simple-address/compare/v0.2.2...v2.0.0

## v0.2.2 - 2025-12-05

### What's fixed

- Fix special treatment for empty non-laravel AdminLevelCollection (#5)
- Fix error handling in geocoding operations with improved logging

**Full Changelog**: https://github.com/el-schneider/statamic-simple-address/compare/v0.2.1...v0.2.2

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
