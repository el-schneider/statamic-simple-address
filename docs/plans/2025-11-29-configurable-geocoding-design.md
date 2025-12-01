# Configurable Geocoding Service Design

**Date:** 2025-11-29
**Status:** Design Review
**Context:** Moving from tightly-coupled Nominatim implementation to a flexible, configurable provider system with built-in caching.

---

## Problem Statement

The current `GeocodingService` hardcodes Nominatim. Users cannot:

- Switch to different geocoding providers (Mapbox, Geoapify, etc.)
- Configure provider-specific options (e.g., Mapbox's permanent geocoding mode)
- Comply with rate limits through response caching
- Customize cache behavior for their deployment

## Solution Overview

Build a configuration-driven provider abstraction that:

- Ships with Nominatim as the default (works out of the box, zero configuration)
- Supports pluggable providers via config
- Includes transparent caching using geocoder-php's `ProviderCache` decorator
- Allows customization through environment variables or published config file
- Follows the principle: **Simple by default – flexible when you need it**

---

## Architecture

### 1. Configuration System

**File:** `config/simple-address.php`

```php
return [
    // Which provider to use (environment variable or default)
    'provider' => env('SIMPLE_ADDRESS_PROVIDER', 'nominatim'),

    // Cache configuration
    'cache' => [
        'enabled' => env('SIMPLE_ADDRESS_CACHE_ENABLED', true),
        'store' => env('SIMPLE_ADDRESS_CACHE_STORE', null),     // null = use Laravel's default
        'duration' => env('SIMPLE_ADDRESS_CACHE_DURATION', 9999999), // seconds (~115 days)
    ],

    // Available providers
    'providers' => [
        'nominatim' => [
            'class' => \Geocoder\Provider\Nominatim\Nominatim::class,
            'factory' => 'withOpenStreetMapServer',  // Static factory method
            'args' => [
                config('app.name', 'Statamic Simple Address'), // user_agent
            ],
        ],
    ],
];
```

**Design decisions:**

- **Nested `args` key:** Constructor arguments group explicitly under `args`, making provider setup self-documenting
- **Factory method support:** Handles providers like Nominatim that use static factory methods (e.g., `Nominatim::withOpenStreetMapServer()`)
- **Environment-driven:** All settings support `.env` overrides for deployment flexibility
- **Reasonable defaults:** Every setting has a sensible value; users start without publishing config

### 2. GeocodingService Enhancement

**File:** `src/Services/GeocodingService.php`

The service:

1. Reads provider configuration at construction time
2. Instantiates the configured provider (with factory method support if needed)
3. Wraps the provider with `ProviderCache` if caching is enabled
4. Exposes the same `geocode()` and `reverse()` public API

**Key methods:**

- `buildProvider()`: Reads config, instantiates the provider with correct constructor arguments
- `getConstructorArgs()`: Extracts the `args` array from config
- `geocode()` and `reverse()`: Unchanged—they work transparently with cached or uncached providers

**Implementation notes:**

- Uses reflection for flexible instantiation: `new $class($httpClient, ...$args)`
- Supports factory methods: `$class::$factory($httpClient, ...$args)`
- Validates provider exists in config; throws clear error when missing

### 3. Caching Strategy

Uses geocoder-php's `ProviderCache` decorator, which:

- Implements the decorator pattern: wraps any provider transparently
- Uses PSR-16 SimpleCache interface (Laravel's cache system complies)
- Hashes queries to cache keys: `sha1((string) $query . $providerName)`
- Stores results with configurable TTL

### Flow

```
User Request → StatefulGeocoder → ProviderCache (if enabled) → Actual Provider
                                        ↓
                              Check cache first
                              If miss: hit API, cache result, return
                              If hit: return cached result
```

Caching is transparent—users ignore it, and it works. They can disable it or adjust TTL via config/env vars.

### 4. ServiceProvider Integration

**File:** `src/ServiceProvider.php`

```php
public function register()
{
    // Singleton: one instance per request
    $this->app->singleton(GeocodingService::class);
}

public function boot()
{
    // Let users publish config for customization
    $this->publishes([
        __DIR__.'/../config/simple-address.php' => config_path('simple-address.php'),
    ], 'simple-address-config');
}
```

- `singleton()` ensures efficient caching within a request
- `publishes()` enables optional customization without forcing it

### 5. Dependencies

Add to `composer.json`:

```json
"geocoder-php/nominatim-provider": "^6.0",
"geocoder-php/cache-provider": "^1.0",
"guzzlehttp/guzzle": "^7.0"
```

---

## User Experience

### Installation (Day 1)

```bash
composer require el-schneider/statamic-simple-address
```

**What happens:**

- Addon installs with Nominatim as default provider
- Caching enables automatically
- No configuration needed—everything works immediately

### Switching Providers (Production)

#### Option A: Environment variable only

```bash
SIMPLE_ADDRESS_PROVIDER=mapbox
MAPBOX_API_KEY=your-token
```

Requires publishing config to add Mapbox provider definition.

#### Option B: Full customization

```bash
php artisan vendor:publish --tag=simple-address-config
```

Edit `config/simple-address.php` to:

- Add new providers
- Adjust cache duration
- Change cache store (e.g., to Redis)
- Disable caching if needed

### Query-Level Customization

For provider-specific query options (e.g., `withData()` in geocoder-php), users pass them in the controller where they have the query object:

```php
$query = GeocodeQuery::create('address');

// Provider-specific options via withData()
if (config('simple-address.provider') === 'mapbox') {
    $query = $query->withData('limit', 5);
}

$results = $geocodingService->geocode($query);
```

---

## Adding New Providers

To support a new provider (e.g., Mapbox):

1. Install the provider: `composer require geocoder-php/mapbox-provider`
2. Publish config: `php artisan vendor:publish --tag=simple-address-config`
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

4. Set env var: `SIMPLE_ADDRESS_PROVIDER=mapbox`

---

## Testing Strategy

### Unit Tests

- **ConfigProvider:** Verify provider instantiation with different configs
- **GeocodingService:** Mock providers, test geocode/reverse with caching on/off
- **ProviderCache:** Verify cache hit/miss behavior (integration test with real cache)

### Feature Tests

- **End-to-end:** Real geocoding requests with Nominatim (when provider is available)
- **Cache behavior:** Verify identical queries return cached results
- **Provider switching:** Test switching between providers via config

---

## Future Extensibility

This design enables:

1. **Custom providers:** Users can extend or create providers following geocoder-php's interface
2. **Cache middleware:** Could add request-level cache headers or rate-limit handling
3. **Provider fallback chains:** Could support Chain provider for redundancy
4. **Monitoring:** Could wrap caching with telemetry (cache hit ratio, API usage)

---

## Constraints & Trade-offs

### Simplicity over Features

- Start with Nominatim + basic caching; extend based on actual user needs
- No multi-provider fallback chains (Chain provider available if needed later)
- No per-field provider overrides (one provider per installation)

### Caching Behavior

- Default cache duration is "forever" (~115 days) because geocoding results stay constant
- Users can override if they need fresher data
- Disabling cache is supported but discouraged (Nominatim enforces rate limits)

### Configuration Publishing

- Config publishing is optional—out-of-the-box experience requires nothing
- When published, config is mutable; users own correctness

---

## Summary

This design delivers:

- ✅ **Simple by default:** Nominatim works immediately, caching enabled, zero config
- ✅ **Flexible when needed:** Users can switch providers, customize cache, adjust settings
- ✅ **Proven patterns:** Uses geocoder-php's standard `ProviderCache` decorator
- ✅ **Extensible:** Adding new providers requires config changes only
- ✅ **Well-bounded:** Clear separation between config, instantiation, and caching
- ✅ **Explicit:** Constructor args nest and document clearly

**Next Steps:**

1. Implement GeocodingService updates
2. Create `config/simple-address.php`
3. Update ServiceProvider for config publishing
4. Add `geocoder-php/cache-provider` to dependencies
5. Write tests for provider instantiation and caching behavior
6. Update README with configuration examples
