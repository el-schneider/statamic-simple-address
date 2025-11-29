# Statamic Simple Address

A simple address autocomplete fieldtype for Statamic. Works out of the box with no API keys needed.

**Simple by default – flexible when you need it.**

## Features

- **Zero-config setup** – Uses Nominatim (OpenStreetMap) by default
- **Backend routing & caching** – Requests go through your backend and are deduped to stay within rate limits
- **Interactive map** – Draggable marker with reverse geocoding
- **YAML preview** – View and verify stored data
- **Multiple providers** – Nominatim, Geoapify, Google, Mapbox built-in
- **Extensible** – Create custom providers by extending `AbstractProvider`

## Quick Start

Add a Simple Address field to your blueprint.

The field stores a normalized address structure:

```yaml
address:
  label: '221B Baker Street, London, NW1 6XE, United Kingdom'
  lat: '51.5237540'
  lon: '-0.1585267'
  street: Baker Street
  houseNumber: 221B
  postcode: NW1 6XE
  city: London
  region: Greater London
  country: United Kingdom
  countryCode: GB
```

> **Note:** The default Nominatim setup is ideal for local development. The public Nominatim server has a strict 1 req/sec limit and forbids autocomplete on the client side. For production, most users switch to Geoapify, Google, or Mapbox – all offer free tiers, but usage limits and terms vary.

## Using Different Providers

Publish the config to switch providers or add custom ones:

```bash
php artisan vendor:publish --tag=simple-address-config
```

Add your API key to `.env`:

| Provider  | Env Variable             | Notes                                   |
| --------- | ------------------------ | --------------------------------------- |
| Nominatim | –                        | Free (public instance: 1 req/sec)       |
| Geoapify  | `GEOAPIFY_API_KEY`       | Allows storing/caching with attribution |
| Google    | `GOOGLE_GEOCODE_API_KEY` | Has restrictions on storing results     |
| Mapbox    | `MAPBOX_ACCESS_TOKEN`    | Permanent storage requires special mode |

**Example: Switch to Mapbox**

```env
MAPBOX_ACCESS_TOKEN=pk.eyJ1Ijo...
SIMPLE_ADDRESS_PROVIDER=mapbox
```

All fields now use Mapbox. Override per field in the field configuration.

## Provider Notes (Storage & Terms)

Different providers have different rules regarding storing geocoded results:

- **Nominatim / OpenStreetMap**
  - Public server: strict rate limit, autocomplete not allowed
  - Data is ODbL-licensed; storing OSM-derived data may trigger share-alike obligations
  - For production autocomplete, use your own instance or a commercial provider

- **Geoapify**
  - Allows caching and storing results; attribution required
  - Good default for production if you want to persist geodata

- **Google Maps Platform**
  - Geocoded coordinates are generally considered temporary cache, with limited retention
  - Some uses with non-Google maps are restricted

- **Mapbox**
  - "Temporary" geocoding (default) does not allow storing results permanently

## Custom Providers

Need a different geocoding API? Extend `AbstractProvider` to integrate any provider. See the built-in providers in `src/Providers/` for examples.
