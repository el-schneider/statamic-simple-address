# Geocoding Service Simplification Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan
> task-by-task.

**Goal:** Replace custom provider abstraction with official Geocoder library (Nominatim default),
consolidate to single global provider, and simplify controller and fieldtype.

**Architecture:** GeocodingService becomes a thin wrapper around Geocoder's official providers
returning raw Address objects. Users can install additional official providers via Composer and
switch via config. Controller validates input and filters results. SimpleAddress fieldtype removes
provider selector.

**Tech Stack:** `geocoder-php/nominatim-provider`, `guzzlehttp/guzzle`, Pest for testing

**Provider Support:** Nominatim by default. Users can install any official `geocoder-php/*-provider`
package and switch via config (e.g., Google Maps, MapBox, OpenCage).

---

## Task 1: Install Geocoder with Nominatim Provider

**Files:**

- Modify: `composer.json`

**Step 1: Add Nominatim and Guzzle**

Run:

```bash
composer require geocoder-php/nominatim-provider guzzlehttp/guzzle
```

This installs:

- `geocoder-php/nominatim-provider` (pulls in core Geocoder)
- `guzzlehttp/guzzle` (PSR-18 HTTP client)

**Step 2: Verify installation**

Run: `composer show geocoder-php/nominatim-provider`

Expected: Shows nominatim-provider version info

**Step 3: Commit**

```bash
git add composer.json composer.lock
git commit -m "chore: add geocoder-php/nominatim-provider and guzzlehttp/guzzle"
```

---

## Task 2: Create Exception Classes

**Files:**

- Create: `src/Exceptions/GeocodingApiException.php`
- Create: `src/Exceptions/ConfigurationException.php`

**Step 1: Create GeocodingApiException**

Create `src/Exceptions/GeocodingApiException.php`:

```php
<?php

namespace ElSchneider\StatamicSimpleAddress\Exceptions;

class GeocodingApiException extends \Exception
{
    private int $statusCode;

    public function __construct(string $message = '', int $statusCode = 500, ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
        $this->statusCode = $statusCode;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
}
```

**Step 2: Create ConfigurationException**

Create `src/Exceptions/ConfigurationException.php`:

```php
<?php

namespace ElSchneider\StatamicSimpleAddress\Exceptions;

class ConfigurationException extends \Exception
{
}
```

**Step 3: Verify classes exist**

Run: `ls -la src/Exceptions/`

Expected: Shows both new files

**Step 4: Commit**

```bash
git add src/Exceptions/GeocodingApiException.php src/Exceptions/ConfigurationException.php
git commit -m "feat: add geocoding exception classes"
```

---

## Task 3: Refactor GeocodingService to Use Geocoder

**Files:**

- Modify: `src/Services/GeocodingService.php` (complete rewrite)
- Test: `tests/Unit/Services/GeocodingServiceTest.php`

**Step 1: Write test for search method**

Update `tests/Unit/Services/GeocodingServiceTest.php`:

```php
<?php

namespace ElSchneider\StatamicSimpleAddress\Tests\Unit\Services;

use ElSchneider\StatamicSimpleAddress\Exceptions\ConfigurationException;
use ElSchneider\StatamicSimpleAddress\Exceptions\GeocodingApiException;
use ElSchneider\StatamicSimpleAddress\Services\GeocodingService;
use Geocoder\Collection;
use Tests\TestCase;

class GeocodingServiceTest extends TestCase
{
    public function test_search_returns_collection_of_addresses()
    {
        config(['simple-address.provider' => 'nominatim']);

        $service = app(GeocodingService::class);
        $result = $service->search('Berlin, Germany', ['countries' => ['de']]);

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertGreaterThan(0, count($result));
    }

    public function test_search_throws_configuration_exception_on_invalid_provider()
    {
        config(['simple-address.provider' => 'nonexistent']);

        $service = app(GeocodingService::class);

        $this->expectException(ConfigurationException::class);
        $service->search('Berlin, Germany');
    }

    public function test_reverse_returns_collection_of_addresses()
    {
        config(['simple-address.provider' => 'nominatim']);

        $service = app(GeocodingService::class);
        $result = $service->reverse(52.5200, 13.4050, ['language' => 'en']);

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertGreaterThan(0, count($result));
    }

    public function test_reverse_throws_configuration_exception_on_invalid_provider()
    {
        config(['simple-address.provider' => 'nonexistent']);

        $service = app(GeocodingService::class);

        $this->expectException(ConfigurationException::class);
        $service->reverse(52.5200, 13.4050);
    }
}
```

**Step 2: Run tests (they will fail)**

Run: `./vendor/bin/pest tests/Unit/Services/GeocodingServiceTest.php -v`

Expected: All tests fail with missing methods or class

**Step 3: Rewrite GeocodingService**

Completely replace `src/Services/GeocodingService.php`:

```php
<?php

namespace ElSchneider\StatamicSimpleAddress\Services;

use ElSchneider\StatamicSimpleAddress\Exceptions\ConfigurationException;
use ElSchneider\StatamicSimpleAddress\Exceptions\GeocodingApiException;
use Geocoder\Collection;
use Geocoder\Exception\Exception as GeocoderException;
use Geocoder\Provider\Provider;
use GuzzleHttp\Client;

class GeocodingService
{
    private Provider $provider;

    public function __construct()
    {
        $providerName = config('simple-address.provider', 'nominatim');
        $this->provider = $this->createProvider($providerName);
    }

    /**
     * Search for addresses by query string
     */
    public function search(string $query, array $options = []): Collection
    {
        try {
            return $this->provider->geocodeQuery(
                \Geocoder\Query\GeocodeQuery::create($query)
                    ->withCountries($options['countries'] ?? [])
                    ->withLocale($options['language'] ?? null)
            );
        } catch (GeocoderException $e) {
            throw new GeocodingApiException(
                sprintf('Geocoding API error: %s', $e->getMessage()),
                500,
                $e
            );
        }
    }

    /**
     * Reverse geocode by coordinates
     */
    public function reverse(float $lat, float $lon, array $options = []): Collection
    {
        try {
            return $this->provider->reverseQuery(
                \Geocoder\Query\ReverseQuery::create($lat, $lon)
                    ->withLocale($options['language'] ?? null)
            );
        } catch (GeocoderException $e) {
            throw new GeocodingApiException(
                sprintf('Reverse geocoding API error: %s', $e->getMessage()),
                500,
                $e
            );
        }
    }

    /**
     * Create the configured provider instance
     */
    private function createProvider(string $providerName): Provider
    {
        $httpClient = new Client();

        return match ($providerName) {
            'nominatim' => $this->createNominatimProvider($httpClient),
            'google' => $this->createGoogleProvider($httpClient),
            'mapbox' => $this->createMapboxProvider($httpClient),
            default => throw new ConfigurationException(
                sprintf('Unknown or unsupported provider: %s. Supported providers: nominatim, google, mapbox', $providerName)
            ),
        };
    }

    /**
     * Create Nominatim provider
     */
    private function createNominatimProvider(Client $httpClient): Provider
    {
        return new \Geocoder\Provider\Nominatim\Nominatim(
            $httpClient,
            config('app.name', 'Statamic Simple Address')
        );
    }

    /**
     * Create Google Maps provider
     */
    private function createGoogleProvider(Client $httpClient): Provider
    {
        $apiKey = config('simple-address.providers.google.api_key');

        if (!$apiKey) {
            throw new ConfigurationException('Google Maps API key not configured. Set GOOGLE_MAPS_API_KEY env var.');
        }

        return new \Geocoder\Provider\GoogleMaps\GoogleMaps($httpClient, $apiKey);
    }

    /**
     * Create MapBox provider
     */
    private function createMapboxProvider(Client $httpClient): Provider
    {
        $apiKey = config('simple-address.providers.mapbox.api_key');

        if (!$apiKey) {
            throw new ConfigurationException('MapBox API key not configured. Set MAPBOX_API_KEY env var.');
        }

        return new \Geocoder\Provider\Mapbox\Mapbox($httpClient, $apiKey);
    }
}
```

**Step 4: Run tests**

Run: `./vendor/bin/pest tests/Unit/Services/GeocodingServiceTest.php -v`

Expected: Tests pass

**Step 5: Commit**

```bash
git add src/Services/GeocodingService.php tests/Unit/Services/GeocodingServiceTest.php
git commit -m "refactor: rewrite GeocodingService to use Geocoder library with Nominatim"
```

---

## Task 4: Simplify GeocodingController

**Files:**

- Modify: `src/Http/Controllers/GeocodingController.php`
- Test: `tests/Feature/Http/Controllers/GeocodingControllerTest.php`

**Step 1: Write test for simplified search**

Update `tests/Feature/Http/Controllers/GeocodingControllerTest.php`:

```php
<?php

namespace ElSchneider\StatamicSimpleAddress\Tests\Feature\Http\Controllers;

use Tests\TestCase;

class GeocodingControllerTest extends TestCase
{
    public function test_search_validates_query()
    {
        $response = $this->postJson('/cp/simple-address/search', [
            'query' => '',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('query');
    }

    public function test_search_returns_results()
    {
        config(['simple-address.provider' => 'nominatim']);

        $response = $this->postJson('/cp/simple-address/search', [
            'query' => 'Berlin, Germany',
            'countries' => ['de'],
            'language' => 'en',
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'results' => [
                '*' => ['label', 'lat', 'lon']
            ]
        ]);
    }
}
```

**Step 2: Run tests (they will fail)**

Run: `./vendor/bin/pest tests/Feature/Http/Controllers/GeocodingControllerTest.php -v`

Expected: Tests fail with current implementation

**Step 3: Rewrite GeocodingController**

Replace `src/Http/Controllers/GeocodingController.php`:

```php
<?php

namespace ElSchneider\StatamicSimpleAddress\Http\Controllers;

use ElSchneider\StatamicSimpleAddress\Exceptions\ConfigurationException;
use ElSchneider\StatamicSimpleAddress\Exceptions\GeocodingApiException;
use ElSchneider\StatamicSimpleAddress\Services\GeocodingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class GeocodingController
{
    public function __construct(
        private GeocodingService $geocodingService,
    ) {}

    public function search(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'query' => 'required|string|min:1|max:255',
                'countries' => 'array',
                'countries.*' => 'string',
                'language' => 'string|nullable',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        }

        try {
            $results = $this->geocodingService->search(
                $validated['query'],
                [
                    'countries' => $validated['countries'] ?? [],
                    'language' => $validated['language'] ?? null,
                ]
            );

            $filtered = $results->map(function ($address) {
                return [
                    'label' => $address->getDisplayName(),
                    'lat' => (string) $address->getCoordinates()->getLatitude(),
                    'lon' => (string) $address->getCoordinates()->getLongitude(),
                    'name' => $address->getStreetName() ?? null,
                    'address' => [
                        'street' => $address->getStreetName(),
                        'postal_code' => $address->getPostalCode(),
                        'city' => $address->getCity(),
                        'county' => $address->getCounty(),
                        'country' => $address->getCountryName(),
                    ],
                ];
            });

            return response()->json(['results' => $filtered->values()], 200);

        } catch (GeocodingApiException $e) {
            Log::warning('simple-address: search API request failed', [
                'query' => $validated['query'] ?? null,
                'status' => $e->getStatusCode(),
            ]);

            return response()->json([
                'message' => $e->getMessage(),
                'status' => $e->getStatusCode(),
            ], 502);

        } catch (ConfigurationException $e) {
            return response()->json(['message' => $e->getMessage()], 400);

        } catch (\Exception $e) {
            Log::error('simple-address: unexpected error during search', [
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Internal server error',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    public function reverse(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'lat' => 'required|numeric|between:-90,90',
                'lon' => 'required|numeric|between:-180,180',
                'language' => 'string|nullable',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        }

        try {
            $results = $this->geocodingService->reverse(
                (float) $validated['lat'],
                (float) $validated['lon'],
                ['language' => $validated['language'] ?? null]
            );

            $filtered = $results->map(function ($address) {
                return [
                    'label' => $address->getDisplayName(),
                    'lat' => (string) $address->getCoordinates()->getLatitude(),
                    'lon' => (string) $address->getCoordinates()->getLongitude(),
                    'name' => $address->getStreetName() ?? null,
                    'address' => [
                        'street' => $address->getStreetName(),
                        'postal_code' => $address->getPostalCode(),
                        'city' => $address->getCity(),
                        'county' => $address->getCounty(),
                        'country' => $address->getCountryName(),
                    ],
                ];
            });

            return response()->json(['results' => $filtered->values()], 200);

        } catch (GeocodingApiException $e) {
            Log::warning('simple-address: reverse geocoding API request failed', [
                'lat' => $validated['lat'] ?? null,
                'lon' => $validated['lon'] ?? null,
                'status' => $e->getStatusCode(),
            ]);

            return response()->json([
                'message' => $e->getMessage(),
                'status' => $e->getStatusCode(),
            ], 502);

        } catch (ConfigurationException $e) {
            return response()->json(['message' => $e->getMessage()], 400);

        } catch (\Exception $e) {
            Log::error('simple-address: unexpected error during reverse geocoding', [
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Internal server error',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }
}
```

**Step 4: Run tests**

Run: `./vendor/bin/pest tests/Feature/Http/Controllers/GeocodingControllerTest.php -v`

Expected: Tests pass

**Step 5: Commit**

```bash
git add src/Http/Controllers/GeocodingController.php tests/Feature/Http/Controllers/GeocodingControllerTest.php
git commit -m "refactor: simplify GeocodingController to use single global provider"
```

---

## Task 5: Simplify SimpleAddress Fieldtype

**Files:**

- Modify: `src/Fieldtypes/SimpleAddress.php`

**Step 1: Remove provider dropdown and simplify**

In `src/Fieldtypes/SimpleAddress.php`, update `configFieldItems()`:

```php
protected function configFieldItems(): array
{
    return [
        'placeholder' => [
            'type' => 'text',
            'display' => __('Placeholder'),
            'default' => __('Start typing …'),
        ],
        'countries' => [
            'type' => 'taggable',
            'display' => __('Countries'),
            'instructions' => __('Change the countries to search in. Two letters country codes (ISO 3166-1 alpha-2). e.g. **gb** for the United Kingdom, **de** for Germany'),
            'width' => 50,
        ],
        'language' => [
            'type' => 'taggable',
            'display' => __('Language'),
            'instructions' => __('Preferred language order for showing search results. Either use a standard RFC2616 (e.g. **en**, **de-CH**, **en-US**) accept-language string or a simple comma-separated list of language codes.'),
            'width' => 50,
            'default' => ['en'],
        ],
        'debounce_delay' => [
            'type' => 'integer',
            'display' => __('Search Debounce Delay'),
            'instructions' => __('Delay in milliseconds before triggering search requests. Nominatim requires at least 1000ms between requests.'),
            'width' => 50,
            'default' => 1000,
            'min' => 1000,
            'max' => 5000,
            'required' => true,
        ],
    ];
}
```

**Step 2: Simplify preload()**

Update `preload()` method:

```php
public function preload(): array
{
    return [
        'provider_min_debounce_delay' => 1000, // Nominatim minimum
    ];
}
```

**Step 3: Run tests**

Run: `./vendor/bin/pest --filter Fieldtype -v`

Expected: Tests pass

**Step 4: Commit**

```bash
git add src/Fieldtypes/SimpleAddress.php
git commit -m "refactor: simplify SimpleAddress fieldtype, remove provider selector"
```

---

## Task 6: Update and Simplify Fixture Generator

**Files:**

- Modify: `scripts/generate-fixtures.php`

**Step 1: Simplify to Nominatim only**

Replace `scripts/generate-fixtures.php`:

```php
#!/usr/bin/env php
<?php

/**
 * Generate API response fixtures for Nominatim geocoding provider.
 *
 * Usage:
 *   php scripts/generate-fixtures.php
 *
 * Environment variables (set in .env or export):
 *   APP_NAME - Application name (used for Nominatim User-Agent)
 *
 * The script saves raw Geocoder results as JSON fixtures in tests/__fixtures__/providers/
 */

require_once __DIR__.'/../vendor/autoload.php';

use Dotenv\Dotenv;
use ElSchneider\StatamicSimpleAddress\Services\GeocodingService;

// Load .env if it exists
if (file_exists(__DIR__.'/../.env')) {
    $dotenv = Dotenv::createImmutable(__DIR__.'/..');
    $dotenv->safeLoad();
}

class FixtureGenerator
{
    private string $fixturesPath;

    private GeocodingService $geocoding;

    private string $searchQuery = 'Berlin, Germany';

    private float $reverseLat = 52.5200;

    private float $reverseLon = 13.4050;

    public function __construct()
    {
        $this->fixturesPath = __DIR__.'/../tests/__fixtures__/providers';
        config(['simple-address.provider' => 'nominatim']);
        $this->geocoding = new GeocodingService;
    }

    public function run(): int
    {
        echo "=== Generating Nominatim Fixtures ===\n";
        echo "Search query: {$this->searchQuery}\n";
        echo "Reverse coords: {$this->reverseLat}, {$this->reverseLon}\n";

        $success = 0;
        $errors = 0;

        // Search fixture
        try {
            $this->generateSearchFixture();
            $success++;
        } catch (Exception $e) {
            echo "   ✗ search: {$e->getMessage()}\n";
            $errors++;
        }

        // Reverse fixture
        try {
            $this->generateReverseFixture();
            $success++;
        } catch (Exception $e) {
            echo "   ✗ reverse: {$e->getMessage()}\n";
            $errors++;
        }

        echo "\n=== Summary ===\n";
        echo "✓ {$success} fixtures generated\n";

        if ($errors > 0) {
            echo "✗ {$errors} errors\n";

            return 1;
        }

        return 0;
    }

    private function generateSearchFixture(): void
    {
        $result = $this->geocoding->search($this->searchQuery, ['countries' => []]);
        $filename = $this->saveFixture('nominatim', 'search', 'berlin_germany', $result);

        echo "   ✓ {$filename}\n";
    }

    private function generateReverseFixture(): void
    {
        $result = $this->geocoding->reverse($this->reverseLat, $this->reverseLon, []);
        $filename = $this->saveFixture('nominatim', 'reverse', 'berlin', $result);

        echo "   ✓ {$filename}\n";
    }

    private function saveFixture(string $provider, string $type, string $name, \Geocoder\Collection $collection): string
    {
        $dir = "{$this->fixturesPath}/{$provider}";

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $filename = "{$type}_{$name}.json";
        $filepath = "{$dir}/{$filename}";

        // Convert Geocoder Collection to array of addresses
        $data = $collection->map(fn ($address) => [
            'display_name' => $address->getDisplayName(),
            'latitude' => $address->getCoordinates()->getLatitude(),
            'longitude' => $address->getCoordinates()->getLongitude(),
            'street_name' => $address->getStreetName(),
            'postal_code' => $address->getPostalCode(),
            'city' => $address->getCity(),
            'county' => $address->getCounty(),
            'country_name' => $address->getCountryName(),
        ])->toArray();

        $prettyJson = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        file_put_contents($filepath, $prettyJson."\n");

        return $filename;
    }
}

$generator = new FixtureGenerator;
exit($generator->run());
```

**Step 2: Run fixture generation**

Run: `php scripts/generate-fixtures.php`

Expected: Generates nominatim fixtures without errors

**Step 3: Commit**

```bash
git add scripts/generate-fixtures.php
git commit -m "refactor: simplify fixture generator for Nominatim only"
```

---

## Task 7: Update Configuration

**Files:**

- Modify: `config/simple-address.php`

**Step 1: Add global provider config with optional providers**

Update `config/simple-address.php`:

```php
<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Geocoding Provider
    |--------------------------------------------------------------------------
    |
    | The geocoding provider to use globally across the addon.
    | Default: 'nominatim' (no API key required)
    |
    | To use other providers, first install them via Composer:
    | - composer require geocoder-php/google-maps-provider
    | - composer require geocoder-php/mapbox-provider
    |
    | Then set SIMPLE_ADDRESS_PROVIDER env var and configure API keys below.
    |
    */
    'provider' => env('SIMPLE_ADDRESS_PROVIDER', 'nominatim'),

    /*
    |--------------------------------------------------------------------------
    | Provider API Keys
    |--------------------------------------------------------------------------
    |
    | API keys for providers that require authentication.
    | Nominatim does not require a key.
    |
    | To use Google Maps or MapBox, install the provider package and set the
    | corresponding API key environment variable:
    |
    | GOOGLE_MAPS_API_KEY
    | MAPBOX_API_KEY
    |
    */
    'providers' => [
        'google' => [
            'api_key' => env('GOOGLE_MAPS_API_KEY'),
        ],
        'mapbox' => [
            'api_key' => env('MAPBOX_API_KEY'),
        ],
    ],
];
```

**Step 2: Verify config loads**

Run:
`php -r "require 'config/simple-address.php'; echo 'Config loads successfully'"`

Expected: "Config loads successfully"

**Step 3: Commit**

```bash
git add config/simple-address.php
git commit -m "refactor: update config for Nominatim default with optional provider switching"
```

---

## Task 8: Run All Tests and Fix Failures

**Files:**

- Test: All test files in `tests/`

**Step 1: Run full test suite**

Run: `./vendor/bin/pest -v`

Expected: Some tests may fail due to changes

**Step 2: Fix any failing tests**

Review test output and fix tests that:

- Still reference old provider parameter or old provider names
- Test old transformation logic
- Reference old exception names

Likely fixes:

- Update any controller tests to not send provider parameter
- Update service tests to expect Geocoder Collection
- Remove any tests for Geoapify/Geocodify/Mapbox since they're unsupported
- Update transformer tests if they exist (may be removed entirely)

**Step 3: Run tests again**

Run: `./vendor/bin/pest -v`

Expected: All tests pass

**Step 4: Commit**

```bash
git add tests/
git commit -m "test: update tests for simplified Nominatim-only geocoding service"
```

---

## Task 9: Code Quality Check

**Files:**

- All modified files

**Step 1: Run formatting check**

Run: `npm run check`

Expected: All checks pass (or warnings only)

**Step 2: Fix any formatting issues**

Run: `npm run fix`

**Step 3: Run check again**

Run: `npm run check`

Expected: All checks pass

**Step 4: Commit if changes**

```bash
git add .
git commit -m "style: apply linting and formatting fixes"
```

---

## Task 10: Verify Integration

**Files:**

- `src/Http/Controllers/GeocodingController.php`
- `src/Services/GeocodingService.php`
- `src/Fieldtypes/SimpleAddress.php`

**Step 1: Test search endpoint manually**

Run:
`curl -X POST http://localhost:8000/cp/simple-address/search -H "Content-Type: application/json" -d '{"query":"Berlin","countries":["de"]}'`

Expected: JSON response with address results

**Step 2: Test reverse endpoint manually**

Run:
`curl -X POST http://localhost:8000/cp/simple-address/reverse -H "Content-Type: application/json" -d '{"lat":52.52,"lon":13.405}'`

Expected: JSON response with address results

**Step 3: Verify fixtures generate**

Run: `php scripts/generate-fixtures.php`

Expected: Nominatim fixtures generated without errors

**Step 4: No commit needed**

This is a verification step only.

---

## Summary

All tasks complete when:

- ✓ Nominatim provider installed via Composer
- ✓ GeocodingService refactored to wrap Geocoder
- ✓ Controller simplified (validation + filtering, no provider param)
- ✓ Fieldtype simplified (no provider selector)
- ✓ Fixture generator simplified (Nominatim only)
- ✓ Config updated for global provider with optional switching
- ✓ All tests passing
- ✓ Code quality checks passing
- ✓ Integration verified
