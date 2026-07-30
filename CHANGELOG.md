# Changelog

## v2.2.1 - 2026-07-30

### What's fixed

- **Map view survives a marker drag** — dragging the pin no longer resets the zoom and center.
- **The dropped point is the saved point** — a drag no longer snaps the marker to the nearest matched place.

Thanks to @eugene-karuna for reporting and diagnosing this in #24.

**Full Changelog**: https://github.com/el-schneider/statamic-simple-address/compare/v2.2.0...v2.2.1

## v2.2.0 - 2026-07-29

### What's new

- **Expand details by default** — a new fieldtype toggle that shows the map and address details as soon as an address is selected, instead of behind the *Show details* button. Defaults to off, so existing fields are unchanged.
- **Map Zoom** — a new fieldtype option for the initial zoom level of the details map, on the Leaflet scale from `0` (the whole world) to `20`. Defaults to `13`, the value that was previously hard-coded.

Both options were contributed by @eugene-karuna in #21 and #22, and landed together in #23.

**Full Changelog**: https://github.com/el-schneider/statamic-simple-address/compare/v2.1.2...v2.2.0

## v2.1.2 - 2026-06-05

### What's fixed

- **Laravel 13 cache compatibility** — Geocoder results are now cached as plain serializable arrays and rehydrated on read, avoiding failures when Laravel 13's `cache.serializable_classes=false` security default blocks cached provider result objects from being unserialized.
- **Non-Nominatim provider cache safety** — The cache fix was validated with Google Maps-style geocoder results so providers with custom result objects no longer break cached autocomplete responses.

Fixes https://github.com/el-schneider/statamic-simple-address/issues/19.

**Full Changelog**: https://github.com/el-schneider/statamic-simple-address/compare/v2.1.1...v2.1.2

## v2.1.1 - 2026-04-28

### What's fixed

- **Support customized CP routes** — Frontend geocoding endpoints now use Statamic's `cp_url()` helper instead of hardcoded `/cp/...` URLs.
- **Fix language locale TypeError** — Geocoding controller now normalizes taggable `language` config arrays to comma-separated strings before calling `withLocale()`.

### Maintenance

- **CI/automation alignment** — Dependabot config, CI workflow split, and Husky/pre-commit checks were aligned with current repository maintenance standards.

**Full Changelog**: https://github.com/el-schneider/statamic-simple-address/compare/v2.1.0...v2.1.1

## v0.2.3 - 2026-04-28

### What's fixed

- Fix control panel route handling for simple-address search/reverse endpoints when CP route is customized (#15)

**Full Changelog**: https://github.com/el-schneider/statamic-simple-address/compare/v0.2.2...v0.2.3

## v2.1.0 - 2026-02-17

### What's new

- **Dark mode map tiles** — Map now reactively switches between CARTO light and dark tile layers based on Statamic's color mode
- **Native UI components** — Details panel uses `<ui-card>` and `<ui-badge>`, toggle button uses `<ui-button>` for consistent CP styling
- **Improved dark mode** — Leaflet controls (zoom, attribution) properly themed for dark mode

### What's changed

- Zoom control moved to bottom-right
- Placeholder text updated to "Search for an address or place..."
- No-results text now uses existing Statamic translations ("No results" / "Start typing to search")
- YAML detail view uses simplified styling with inherited theme colors
- CI: Added Composer install step for asset builds

### Contributors

Thanks to @daun for the original PRs (#7, #8, #9) that this release is based on.

**Full Changelog**: https://github.com/el-schneider/statamic-simple-address/compare/v2.0.0...v2.1.0

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
