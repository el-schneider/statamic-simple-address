<img src="images/saf_banner.png" alt="Auto Alt Text">

> **Disclaimer**: Statamic Simple Address recently underwent a complete rewrite and is now under active development, so features and APIs may change.

# Statamic Simple Address

A simple address autocomplete fieldtype for Statamic. Works out of the box with no API keys needed.

**Simple by default – flexible when needed.**

## Features

- **Zero-config setup** – Uses Nominatim (OpenStreetMap) by default
- **Backend routing** – Requests go through your backend and are deduped
- **Interactive map** – Draggable marker with reverse geocoding
- **YAML preview** – View and verify stored data
- **Any geocoder-php provider** – Works with 30+ providers from [geocoder-php](https://geocoder-php.org/). Pre-configured examples for Nominatim, Google Maps, and Mapbox

## Quick Start

Add a Simple Address field to your blueprint.

The field stores a normalized address structure:

```yaml
label: London, England, United Kingdom
lat: 51.5072178
lon: -0.1275862
providedBy: google_maps
bounds:
  south: 51.38494009999999
  west: -0.3514683
  north: 51.6723432
  east: 0.148271
locality: London
adminLevels:
  1:
    name: England
    code: England
    level: 1
  2:
    name: Greater London
    code: Greater London
    level: 2
country: United Kingdom
countryCode: GB
```

> **Note:** The default Nominatim setup is ideal for local development. The public Nominatim server has a strict 1 req/sec limit and forbids autocomplete on the client side. For production, most users switch to Geoapify, Google, or Mapbox – all offer free tiers, but usage limits and terms vary.

## Configuration

```bash
composer require el-schneider/statamic-simple-address
```

### Control Panel Assets (Statamic v6)

If you install/update the addon via Composer, publish the Control Panel assets so Statamic can load the Vite manifest:

```bash
php artisan vendor:publish --tag=statamic-simple-address --force
```

If you install via Statamic's addon installer, this is typically done automatically.

Works immediately with Nominatim as the default provider.

### Using a Different Provider

To switch from Nominatim to another provider:

1. Install the provider package
2. Publish the config and add/uncomment the provider entry
3. Set the required environment variables

```bash
php artisan vendor:publish --tag=simple-address-config
```

#### Google Maps

```bash
composer require geocoder-php/google-maps-provider
```

```ini
SIMPLE_ADDRESS_PROVIDER=google
GOOGLE_GEOCODE_API_KEY=your-api-key
```

#### Mapbox

```bash
composer require geocoder-php/mapbox-provider
```

```ini
SIMPLE_ADDRESS_PROVIDER=mapbox
MAPBOX_API_KEY=your-token
```

The published config includes commented examples for Google and Mapbox. For other providers, add an entry to `config/simple-address.php`:

```php
'my_provider' => [
    'class' => \Geocoder\Provider\MyProvider\MyProvider::class,
    'args' => [env('MY_PROVIDER_API_KEY')],
],
```

See [Geocoder PHP docs](https://geocoder-php.org/docs/#providers) for available providers and their constructor arguments. Thanks to [geocoder-php](https://github.com/geocoder-php/Geocoder) for making multi-provider geocoding easy.
