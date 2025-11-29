# Geocoding Service Simplification Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan
> task-by-task.

**Goal:** Replace custom provider abstraction with Geocoder library, consolidate to single global
provider, and simplify controller and fieldtype.

**Architecture:** GeocodingService becomes a thin Geocoder wrapper returning raw Address objects.
Controller validates input and filters results. SimpleAddress fieldtype removes provider selector.
Fixture generator saves raw results for testing all providers.

**Tech Stack:** Geocoder library, Laravel HTTP, Pest for testing

---

## Task 1: Install Geocoder Library

**Files:**

- Modify: `composer.json`

**Step 1: Add Geocoder package**

Run: `composer require geocoder-php/geocoder`

This adds the Geocoder library as a dependency. The main package handles initialization and
provider registration.

**Step 2: Verify installation**

Run: `composer show geocoder-php/geocoder`

Expected: Shows Geocoder library version info

**Step 3: Commit**

```bash
git add composer.json composer.lock
git commit -m "chore: add geocoder-php/geocoder dependency"
```

---

## Task 2: Create Exception Classes

**Files:**

- Create: `src/Exceptions/GeocodingApiException.php`
- Create: `src/Exceptions/ConfigurationException.php`
- Modify: `src/Exceptions/ProviderApiException.php` (rename to GeocodingApiException, or keep both
  during transition)

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

    public function test_search_throws_configuration_exception_on_missing_provider()
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

    public function test_reverse_throws_configuration_exception_on_missing_provider()
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

Expected: All tests fail with "GeocodingService not found" or missing methods

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
use Geocoder\ProviderAggregator;
use Http\Adapter\Guzzle7\Client as GuzzleAdapter;

class GeocodingService
{
    private ProviderAggregator $geocoder;

    public function __construct()
    {
        $this->geocoder = new ProviderAggregator();
        $httpClient = new GuzzleAdapter();

        $this->geocoder->registerProviders([
            $this->createNominatimProvider($httpClient),
            $this->createGeoapifyProvider($httpClient),
            $this->createGoogleProvider($httpClient),
        ]);
    }

    /**
     * Search for addresses by query string
     */
    public function search(string $query, array $options = []): Collection
    {
        $provider = $this->getActiveProvider();

        try {
            return $this->geocoder->using($provider)->geocodeQuery(
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
        $provider = $this->getActiveProvider();

        try {
            return $this->geocoder->using($provider)->reverseQuery(
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
     * Get available provider names
     */
    public function getAvailableProviders(): array
    {
        return ['nominatim', 'geoapify', 'google'];
    }

    /**
     * Get the active provider name from config
     */
    private function getActiveProvider(): string
    {
        $provider = config('simple-address.provider');

        if (!$provider || !in_array($provider, $this->getAvailableProviders())) {
            throw new ConfigurationException(
                sprintf('Invalid or missing provider: %s', $provider)
            );
        }

        return $provider;
    }

    /**
     * Create Nominatim provider
     */
    private function createNominatimProvider($httpClient): Provider
    {
        return new \Geocoder\Provider\Nominatim\Nominatim(
            $httpClient,
            config('app.name', 'Statamic Simple Address')
        );
    }

    /**
     * Create Geoapify provider
     */
    private function createGeoapifyProvider($httpClient): Provider
    {
        $apiKey = config('simple-address.providers.geoapify.api_key');

        if (!$apiKey) {
            throw new ConfigurationException('Geoapify API key not configured');
        }

        return new \Geocoder\Provider\Geoapify\Geoapify(
            $httpClient,
            $apiKey
        );
    }

    /**
     * Create Google provider
     */
    private function createGoogleProvider($httpClient): Provider
    {
        $apiKey = config('simple-address.providers.google.api_key');

        if (!$apiKey) {
            throw new ConfigurationException('Google API key not configured');
        }

        return new \Geocoder\Provider\GoogleMaps\GoogleMaps(
            $httpClient,
            $apiKey
        );
    }
}
```

**Step 4: Run tests**

Run: `./vendor/bin/pest tests/Unit/Services/GeocodingServiceTest.php -v`

Expected: Tests pass (may need live API calls or fixtures for integration tests)

**Step 5: Commit**

```bash
git add src/Services/GeocodingService.php tests/Unit/Services/GeocodingServiceTest.php
git commit -m "refactor: rewrite GeocodingService to use Geocoder library"
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

    public function test_search_returns_cache_headers()
    {
        config(['simple-address.provider' => 'nominatim']);

        $response = $this->postJson('/cp/simple-address/search', [
            'query' => 'Berlin, Germany',
        ]);

        $response->assertStatus(200);
        $this->assertIn($response->headers->get('X-Cache'), ['HIT', 'MISS']);
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
                    'type' => null,
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
                    'type' => null,
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

**Step 1: Remove provider dropdown**

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
            'instructions' => __('Delay in milliseconds before triggering search requests. This is a frontend optimization to reduce API calls while typing. The backend enforces each provider\'s minimum delay requirement automatically.'),
            'width' => 50,
            'default' => 300,
            'min' => 100,
            'max' => 2000,
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
    $geocodingService = app(GeocodingService::class);
    $provider = config('simple-address.provider', 'nominatim');

    return [
        'provider' => $provider,
        'provider_min_debounce_delay' => 1000, // Nominatim default
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

## Task 6: Update Fixture Generator

**Files:**

- Modify: `scripts/generate-fixtures.php`

**Step 1: Update FixtureGenerator to use new service**

Replace the fixture generation methods in `scripts/generate-fixtures.php`:

```php
private function generateSearchFixture(string $provider): void
{
    $previousProvider = config('simple-address.provider');
    config(['simple-address.provider' => $provider]);

    $result = $this->geocoding->search($this->searchQuery, ['countries' => []]);

    $filename = $this->saveFixture($provider, 'search', 'berlin_germany', $result);

    echo "   ✓ {$filename}\n";

    config(['simple-address.provider' => $previousProvider]);
}

private function generateReverseFixture(string $provider): void
{
    $previousProvider = config('simple-address.provider');
    config(['simple-address.provider' => $provider]);

    $result = $this->geocoding->reverse($this->reverseLat, $this->reverseLon, []);

    $filename = $this->saveFixture($provider, 'reverse', 'berlin', $result);

    echo "   ✓ {$filename}\n";

    config(['simple-address.provider' => $previousProvider]);
}

private function saveFixture(string $provider, string $type, string $name, \Geocoder\Collection $collection): string
{
    $dir = "{$this->fixturesPath}/{$provider}";

    if (! is_dir($dir)) {
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
```

**Step 2: Run fixture generation**

Run: `php scripts/generate-fixtures.php nominatim`

Expected: Generates fixture files without errors

**Step 3: Commit**

```bash
git add scripts/generate-fixtures.php
git commit -m "refactor: update fixture generator to use new GeocodingService"
```

---

## Task 7: Update Configuration

**Files:**

- Modify: `config/simple-address.php`

**Step 1: Add global provider config**

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
    | Supported: 'nominatim', 'geoapify', 'google'
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
    */
    'providers' => [
        'geoapify' => [
            'api_key' => env('GEOAPIFY_API_KEY'),
        ],
        'google' => [
            'api_key' => env('GOOGLE_GEOCODE_API_KEY'),
        ],
    ],
];
```

**Step 2: Verify config loads**

Run: `php -r "require 'config/simple-address.php'; echo 'Config loads successfully'"`

Expected: "Config loads successfully"

**Step 3: Commit**

```bash
git add config/simple-address.php
git commit -m "refactor: update config for single global provider"
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

- Still reference old provider parameter
- Test old transformation logic
- Reference old exception names

Likely fixes:

- Update controller tests to not send provider parameter
- Update service tests to expect Geocoder Collection
- Update any transformer tests (may be removed entirely)

**Step 3: Run tests again**

Run: `./vendor/bin/pest -v`

Expected: All tests pass

**Step 4: Commit**

```bash
git add tests/
git commit -m "test: update tests for simplified geocoding service"
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

Run: `curl -X POST http://localhost:8000/cp/simple-address/search -H "Content-Type: application/json" -d
'{"query":"Berlin","countries":["de"]}'`

Expected: JSON response with address results

**Step 2: Test reverse endpoint manually**

Run: `curl -X POST http://localhost:8000/cp/simple-address/reverse -H "Content-Type: application/json" -d
'{"lat":52.52,"lon":13.405}'`

Expected: JSON response with address results

**Step 3: Verify fixtures generate**

Run: `php scripts/generate-fixtures.php`

Expected: All fixtures generated without errors

**Step 4: Commit**

This is a verification step, no code changes to commit if everything works.

---

## Summary

All tasks complete when:

- ✓ Geocoder library installed
- ✓ GeocodingService refactored to wrap Geocoder
- ✓ Controller simplified (validation + filtering, no provider param)
- ✓ Fieldtype simplified (no provider selector)
- ✓ Fixture generator updated
- ✓ Config updated for global provider
- ✓ All tests passing
- ✓ Code quality checks passing
- ✓ Integration verified
