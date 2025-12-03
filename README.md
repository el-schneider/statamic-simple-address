<img src="images/saf_banner.png" alt="Auto Alt Text">

> **Disclaimer**: Statamic Simple Address is still in its early stages and under active development, so features and APIs may change.

# Statamic Simple Address

A simple address autocomplete fieldtype for Statamic. Works out of the box with no API keys needed.

**Simple by default – flexible when needed.**

## Features

- **Zero-config setup** – Uses Nominatim (OpenStreetMap) by default
- **Backend routing & caching** – Requests go through your backend and are deduped to stay within rate limits
- **Interactive map** – Draggable marker with reverse geocoding
- **YAML preview** – View and verify stored data
- **Any geocoder-php provider** – Works with 30+ providers from [geocoder-php](https://geocoder-php.org/). Pre-configured examples for Nominatim, Google Maps, and Mapbox

## Quick Start

Add a Simple Address field to your blueprint.

The field stores a normalized address structure. The search and reverse geocoding API endpoints return results in the following format:

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

### Basic Setup (Out of the Box)

The addon ships with Nominatim as the default provider and caching enabled. No configuration needed—everything works immediately:

```bash
composer require el-schneider/statamic-simple-address
```

### Customizing Providers

To switch to a different provider or customize settings, you can:

#### Option 1: Environment Variables

```bash
SIMPLE_ADDRESS_PROVIDER=mapbox
SIMPLE_ADDRESS_CACHE_DURATION=3600
SIMPLE_ADDRESS_CACHE_ENABLED=false
```

#### Option 2: Publish Configuration

```bash
php artisan vendor:publish --tag=simple-address-config
```

Edit `config/simple-address.php` to add providers or adjust cache settings.

#### Example Configurations

The published config file includes example configurations for popular providers (Mapbox, Google Maps) commented out. To use them:

1. **Uncomment** the provider configuration in `config/simple-address.php`
2. **Install** the corresponding Geocoder provider package
3. **Set** the required API key via environment variables

For example, to use Google Maps:

```bash
# 1. Install the provider
composer require geocoder-php/google-maps-provider

# 2. Uncomment 'google' in config/simple-address.php

# 3. Set your API key
GOOGLE_GEOCODE_API_KEY=your-api-key
SIMPLE_ADDRESS_PROVIDER=google
```

See [Geocoder PHP docs](https://geocoder-php.org/docs/#providers) for available providers, their constructor arguments, and setup requirements.

### Adding a New Provider

1. Install the provider package:

   ```bash
   composer require geocoder-php/mapbox-provider
   ```

2. Publish config:

   ```bash
   php artisan vendor:publish --tag=simple-address-config
   ```

3. Add to `config/simple-address.php`:

   ```php
   'mapbox' => [
       'class' => \Geocoder\Provider\Mapbox\Mapbox::class,
       'args' => [
           env('MAPBOX_API_KEY'),
           env('MAPBOX_GEOCODING_MODE', 'mapbox.places'),
       ],
   ],
   ```

4. Set environment variable:

   ```bash
   SIMPLE_ADDRESS_PROVIDER=mapbox
   MAPBOX_API_KEY=your-token
   ```

### Caching

Caching is enabled by default using Laravel's cache system. To customize:

- **Disable caching:** `SIMPLE_ADDRESS_CACHE_ENABLED=false`
- **Change duration:** `SIMPLE_ADDRESS_CACHE_DURATION=7200` (in seconds)
- **Use different store:** `SIMPLE_ADDRESS_CACHE_STORE=redis` (must be defined in `config/cache.php`)

## Provider Notes (Storage & Terms)

Different providers have different rules regarding storing geocoded results:

- **Nominatim / OpenStreetMap**
  - Public server: strict rate limit, autocomplete not allowed
  - Data is ODbL-licensed; storing OSM-derived data may trigger share-alike obligations
  - For production autocomplete, use your own instance or a commercial provider

- **Google Maps Platform**
  - Geocoded coordinates are generally considered temporary cache, with limited retention
  - Some uses with non-Google maps are restricted

- **Mapbox**
  - Uses permanent geocoding mode, allowing stored results
  - Requires valid payment method or enterprise contract on your Mapbox account
