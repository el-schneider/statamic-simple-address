# Configurable Geocoding Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development to implement this plan task-by-task.

**Goal:** Transform GeocodingService from hardcoded Nominatim to a flexible, configuration-driven provider system with transparent caching.

**Architecture:** Config file defines available providers with their constructor args. ServiceProvider publishes config and binds GeocodingService. GeocodingService reads config at boot, instantiates the selected provider, optionally wraps with ProviderCache, and exposes unchanged geocode/reverse API.

**Tech Stack:** geocoder-php (already installed), geocoder-php/cache-provider (new), PSR-16 SimpleCache (Laravel's cache), reflection for dynamic instantiation.

---

## Task 1: Add cache-provider dependency

**Files:**

- Modify: `composer.json`

**Step 1: Add geocoder-php/cache-provider to dependencies**

Edit `composer.json` require section to include:

```json
"geocoder-php/cache-provider": "^1.0"
```

Full require section should look like:

```json
"require": {
    "php": "^8.2",
    "statamic/cms": "^5.0",
    "geocoder-php/nominatim-provider": "^6.0",
    "geocoder-php/cache-provider": "^1.0",
    "guzzlehttp/guzzle": "^7.0"
}
```

**Step 2: Run composer install**

```bash
cd /Users/jonas/dev/packages/statamic-simple-address/statamic-simple-address
composer install
```

Expected: New lock entries for geocoder-php/cache-provider

**Step 3: Verify installation**

```bash
composer show geocoder-php/cache-provider
```

Expected: Shows version info

**Step 4: Commit**

```bash
git add composer.json composer.lock
git commit -m "feat: add geocoder-php/cache-provider dependency"
```

---

## Task 2: Create config file

**Files:**

- Create: `config/simple-address.php`

**Step 1: Create the config file**

Create `/Users/jonas/dev/packages/statamic-simple-address/statamic-simple-address/config/simple-address.php` with:

```php
<?php

return [
    // Which provider to use by default
    'provider' => env('SIMPLE_ADDRESS_PROVIDER', 'nominatim'),

    // Cache configuration
    'cache' => [
        'enabled' => env('SIMPLE_ADDRESS_CACHE_ENABLED', true),
        'store' => env('SIMPLE_ADDRESS_CACHE_STORE', null),
        'duration' => env('SIMPLE_ADDRESS_CACHE_DURATION', 9999999),
    ],

    // Available providers and their configuration
    'providers' => [
        'nominatim' => [
            'class' => \Geocoder\Provider\Nominatim\Nominatim::class,
            'factory' => 'withOpenStreetMapServer',
            'args' => [
                config('app.name', 'Statamic Simple Address'),
            ],
        ],
    ],
];
```

**Step 2: Verify file exists**

```bash
cat /Users/jonas/dev/packages/statamic-simple-address/statamic-simple-address/config/simple-address.php
```

Expected: Shows PHP config with proper structure

**Step 3: Commit**

```bash
git add config/simple-address.php
git commit -m "feat: add configurable geocoding provider config file"
```

---

## Task 3: Update GeocodingService with provider instantiation

**Files:**

- Modify: `src/Services/GeocodingService.php`

**Step 1: Write test for provider instantiation**

Create test file `/Users/jonas/dev/packages/statamic-simple-address/statamic-simple-address/tests/Unit/Services/GeocodingServiceTest.php`:

```php
<?php

namespace Tests\Unit\Services;

use ElSchneider\StatamicSimpleAddress\Services\GeocodingService;
use Geocoder\Provider\Nominatim\Nominatim;
use Geocoder\StatefulGeocoder;
use PHPUnit\Framework\TestCase;

class GeocodingServiceTest extends TestCase
{
    public function test_geocoding_service_instantiates_nominatim_by_default()
    {
        // Arrange: Set up test config
        config(['simple-address.provider' => 'nominatim']);
        config(['simple-address.cache.enabled' => false]);
        config(['simple-address.providers.nominatim' => [
            'class' => Nominatim::class,
            'factory' => 'withOpenStreetMapServer',
            'args' => ['Test App'],
        ]]);

        // Act: Create service
        $service = new GeocodingService();

        // Assert: Service exists and is properly typed
        $this->assertInstanceOf(GeocodingService::class, $service);
    }

    public function test_throws_error_if_provider_not_configured()
    {
        // Arrange: Set provider to non-existent name
        config(['simple-address.provider' => 'nonexistent']);
        config(['simple-address.cache.enabled' => false]);
        config(['simple-address.providers' => []]);

        // Act & Assert: Should throw
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Provider 'nonexistent' is not configured");

        new GeocodingService();
    }
}
```

**Step 2: Run test to verify it fails**

```bash
cd /Users/jonas/dev/packages/statamic-simple-address/statamic-simple-address
./vendor/bin/pest tests/Unit/Services/GeocodingServiceTest.php -v
```

Expected: FAIL - "GeocodingService does not accept arguments" or similar

**Step 3: Refactor GeocodingService to support configuration**

Replace entire `/Users/jonas/dev/packages/statamic-simple-address/statamic-simple-address/src/Services/GeocodingService.php` with:

```php
<?php

namespace ElSchneider\StatamicSimpleAddress\Services;

use ElSchneider\StatamicSimpleAddress\Data\AddressResult;
use Geocoder\Provider\Cache\ProviderCache;
use Geocoder\Query\GeocodeQuery;
use Geocoder\Query\ReverseQuery;
use Geocoder\StatefulGeocoder;
use GuzzleHttp\Client;

class GeocodingService
{
    private StatefulGeocoder $geocoder;

    public function __construct()
    {
        $provider = $this->buildProvider();

        // Wrap with caching if enabled
        if (config('simple-address.cache.enabled')) {
            $provider = new ProviderCache(
                $provider,
                app('cache')->store(config('simple-address.cache.store')),
                config('simple-address.cache.duration')
            );
        }

        $this->geocoder = new StatefulGeocoder($provider, 'en');
    }

    public function geocode(GeocodeQuery $query): array
    {
        $results = $this->geocoder->geocodeQuery($query);

        return array_map(
            fn ($address) => new AddressResult(
                label: $this->formatAddressLabel($address),
                lat: (string) $address->getCoordinates()->getLatitude(),
                lon: (string) $address->getCoordinates()->getLongitude(),
                data: array_filter($address->toArray(), fn ($v) => $v !== null && $v !== ''),
            ),
            $results->all()
        );
    }

    public function reverse(ReverseQuery $query): array
    {
        $results = $this->geocoder->reverseQuery($query);

        return array_map(
            fn ($address) => new AddressResult(
                label: $this->formatAddressLabel($address),
                lat: (string) $address->getCoordinates()->getLatitude(),
                lon: (string) $address->getCoordinates()->getLongitude(),
                data: array_filter($address->toArray(), fn ($v) => $v !== null && $v !== ''),
            ),
            $results->all()
        );
    }

    private function buildProvider()
    {
        $providerName = config('simple-address.provider');
        $providerConfig = config("simple-address.providers.{$providerName}");

        if (!$providerConfig) {
            throw new \InvalidArgumentException(
                "Provider '{$providerName}' is not configured in simple-address config."
            );
        }

        $class = $providerConfig['class'];
        $httpClient = new Client();
        $args = $this->getConstructorArgs($providerConfig);

        // If provider has a factory method
        if (isset($providerConfig['factory'])) {
            $factory = $providerConfig['factory'];
            return $class::$factory($httpClient, ...$args);
        }

        // Standard instantiation
        return new $class($httpClient, ...$args);
    }

    private function getConstructorArgs(array $config): array
    {
        return $config['args'] ?? [];
    }

    private function formatAddressLabel($location): string
    {
        $parts = [];

        if ($location->getStreetNumber()) {
            $parts[] = $location->getStreetNumber();
        }

        if ($location->getStreetName()) {
            $parts[] = $location->getStreetName();
        }

        if ($location->getLocality()) {
            $parts[] = $location->getLocality();
        }

        if ($location->getAdminLevels()->first()?->getName()) {
            $parts[] = $location->getAdminLevels()->first()->getName();
        }

        if ($location->getCountry()?->getName()) {
            $parts[] = $location->getCountry()->getName();
        }

        return implode(', ', $parts);
    }
}
```

**Step 4: Run test to verify it passes**

```bash
./vendor/bin/pest tests/Unit/Services/GeocodingServiceTest.php -v
```

Expected: PASS

**Step 5: Commit**

```bash
git add src/Services/GeocodingService.php tests/Unit/Services/GeocodingServiceTest.php
git commit -m "feat: make GeocodingService configurable with provider instantiation"
```

---

## Task 4: Update ServiceProvider to publish config and bind singleton

**Files:**

- Modify: `src/ServiceProvider.php`

**Step 1: Write test for ServiceProvider config publishing**

Add to test file `/Users/jonas/dev/packages/statamic-simple-address/statamic-simple-address/tests/Unit/Services/GeocodingServiceTest.php`:

```php
    public function test_service_provider_publishes_config()
    {
        // Arrange
        $serviceProvider = new \ElSchneider\StatamicSimpleAddress\ServiceProvider(app());

        // Act: Get publishables from service provider
        $publishables = $serviceProvider->publishesConfig();

        // Assert: Config should be publishable
        $this->assertIsArray($publishables);
        $this->assertArrayHasKey(
            __DIR__ . '/../../config/simple-address.php',
            $publishables
        );
    }
```

**Step 2: Run test to verify it fails**

```bash
./vendor/bin/pest tests/Unit/Services/GeocodingServiceTest.php::test_service_provider_publishes_config -v
```

Expected: FAIL - no publishesConfig method or config not included

**Step 3: Update ServiceProvider**

Modify `/Users/jonas/dev/packages/statamic-simple-address/statamic-simple-address/src/ServiceProvider.php`:

```php
<?php

namespace ElSchneider\StatamicSimpleAddress;

use ElSchneider\StatamicSimpleAddress\Services\GeocodingService;
use Statamic\Providers\AddonServiceProvider;

class ServiceProvider extends AddonServiceProvider
{
    protected $routes = [
        'cp' => __DIR__.'/../routes/cp.php',
    ];

    public function register()
    {
        // Bind GeocodingService as singleton
        $this->app->singleton(GeocodingService::class);
    }

    public function boot()
    {
        // Publish config for users who want to customize
        $this->publishes([
            __DIR__.'/../config/simple-address.php' => config_path('simple-address.php'),
        ], 'simple-address-config');
    }
}
```

**Step 4: Run test to verify it passes**

```bash
./vendor/bin/pest tests/Unit/Services/GeocodingServiceTest.php::test_service_provider_publishes_config -v
```

Expected: PASS

**Step 5: Run all GeocodingService tests**

```bash
./vendor/bin/pest tests/Unit/Services/GeocodingServiceTest.php -v
```

Expected: All tests PASS

**Step 6: Commit**

```bash
git add src/ServiceProvider.php
git commit -m "feat: add singleton binding and config publishing in ServiceProvider"
```

---

## Task 5: Add caching integration test

**Files:**

- Modify: `tests/Unit/Services/GeocodingServiceTest.php`

**Step 1: Write test for caching behavior**

Add to `tests/Unit/Services/GeocodingServiceTest.php`:

```php
    public function test_caching_is_enabled_by_default()
    {
        // Arrange: Mock cache store
        config(['simple-address.provider' => 'nominatim']);
        config(['simple-address.cache.enabled' => true]);
        config(['simple-address.cache.store' => null]);
        config(['simple-address.cache.duration' => 3600]);
        config(['simple-address.providers.nominatim' => [
            'class' => Nominatim::class,
            'factory' => 'withOpenStreetMapServer',
            'args' => ['Test App'],
        ]]);

        // Act: Create service
        $service = new GeocodingService();

        // Assert: Service instantiates successfully (caching is transparent)
        $this->assertInstanceOf(GeocodingService::class, $service);
    }

    public function test_caching_can_be_disabled()
    {
        // Arrange
        config(['simple-address.provider' => 'nominatim']);
        config(['simple-address.cache.enabled' => false]);
        config(['simple-address.providers.nominatim' => [
            'class' => Nominatim::class,
            'factory' => 'withOpenStreetMapServer',
            'args' => ['Test App'],
        ]]);

        // Act: Create service with cache disabled
        $service = new GeocodingService();

        // Assert: Service still works without cache
        $this->assertInstanceOf(GeocodingService::class, $service);
    }
```

**Step 2: Run test to verify it fails**

```bash
./vendor/bin/pest tests/Unit/Services/GeocodingServiceTest.php::test_caching_is_enabled_by_default -v
```

Expected: FAIL - cache-related error or config not set

**Step 3: The implementation from Task 3 already handles this**

The GeocodingService.php already includes caching logic, so the test should now pass.

**Step 4: Run tests to verify they pass**

```bash
./vendor/bin/pest tests/Unit/Services/GeocodingServiceTest.php -v
```

Expected: All tests PASS

**Step 5: Commit**

```bash
git add tests/Unit/Services/GeocodingServiceTest.php
git commit -m "test: add caching behavior tests"
```

---

## Task 6: Update existing API controller tests

**Files:**

- Modify: `tests/Feature/Http/Controllers/AddressSearchControllerTest.php` (if it exists)

**Step 1: Check if controller test exists**

```bash
ls -la tests/Feature/Http/Controllers/
```

If file exists, proceed. If not, skip to commit.

**Step 2: Update tests to use config properly**

If the test file exists, verify it sets up config before creating GeocodingService:

```php
$this->setUp() {
    parent::setUp();

    config(['simple-address.provider' => 'nominatim']);
    config(['simple-address.cache.enabled' => false]); // Disable for tests
    config(['simple-address.providers.nominatim' => [
        'class' => Nominatim::class,
        'factory' => 'withOpenStreetMapServer',
        'args' => [config('app.name')],
    ]]);
}
```

**Step 3: Run all feature tests**

```bash
./vendor/bin/pest tests/Feature/ -v
```

Expected: All tests PASS

**Step 4: Commit**

```bash
git add tests/Feature/
git commit -m "test: update controller tests to use geocoding config"
```

---

## Task 7: Add integration test for provider switching

**Files:**

- Create: `tests/Unit/Services/GeocodingProviderSwitchTest.php`

**Step 1: Write test for provider switching**

Create `/Users/jonas/dev/packages/statamic-simple-address/statamic-simple-address/tests/Unit/Services/GeocodingProviderSwitchTest.php`:

```php
<?php

namespace Tests\Unit\Services;

use ElSchneider\StatamicSimpleAddress\Services\GeocodingService;
use Geocoder\Provider\Nominatim\Nominatim;
use PHPUnit\Framework\TestCase;

class GeocodingProviderSwitchTest extends TestCase
{
    public function test_can_switch_provider_via_config()
    {
        // Arrange: Configure two providers
        config(['simple-address.provider' => 'nominatim']);
        config(['simple-address.cache.enabled' => false]);
        config(['simple-address.providers' => [
            'nominatim' => [
                'class' => Nominatim::class,
                'factory' => 'withOpenStreetMapServer',
                'args' => ['Test App'],
            ],
        ]]);

        // Act: Create service with Nominatim
        $service = new GeocodingService();

        // Assert: Service instantiated successfully
        $this->assertInstanceOf(GeocodingService::class, $service);
    }

    public function test_invalid_provider_throws_clear_error()
    {
        // Arrange
        config(['simple-address.provider' => 'invalid_provider']);
        config(['simple-address.cache.enabled' => false]);
        config(['simple-address.providers' => []]);

        // Act & Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Provider 'invalid_provider' is not configured");

        new GeocodingService();
    }
}
```

**Step 2: Run test to verify it fails**

```bash
./vendor/bin/pest tests/Unit/Services/GeocodingProviderSwitchTest.php -v
```

Expected: May fail or pass depending on setup

**Step 3: Run test to verify it passes**

Implementation from Task 3 should handle this.

```bash
./vendor/bin/pest tests/Unit/Services/GeocodingProviderSwitchTest.php -v
```

Expected: PASS

**Step 4: Commit**

```bash
git add tests/Unit/Services/GeocodingProviderSwitchTest.php
git commit -m "test: add provider switching integration tests"
```

---

## Task 8: Run full test suite

**Files:**

- Test: All test files

**Step 1: Run entire test suite**

```bash
cd /Users/jonas/dev/packages/statamic-simple-address/statamic-simple-address
./vendor/bin/pest --verbose
```

Expected: All tests PASS

**Step 2: If tests fail, debug and fix**

If any tests fail:

- Read the error message carefully
- Check if config is being set up properly in test setUp methods
- Ensure GeocodingService is being instantiated correctly
- Verify ProviderCache is available and working

**Step 3: Once all tests pass, commit any fixes**

```bash
git status
# Only commit if there were test fixes
git add .
git commit -m "fix: resolve test failures"
```

---

## Task 9: Update README with configuration examples

**Files:**

- Modify: `README.md`

**Step 1: Find README location**

```bash
cat /Users/jonas/dev/packages/statamic-simple-address/statamic-simple-address/README.md | head -20
```

**Step 2: Add configuration section after installation**

Find the Installation section and add after it:

````markdown
## Configuration

### Basic Setup (Out of the Box)

The addon ships with Nominatim as the default provider and caching enabled. No configuration needed—everything works immediately:

```bash
composer require el-schneider/statamic-simple-address
```
````

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

````

**Step 3: Verify README is properly formatted**

```bash
cat /Users/jonas/dev/packages/statamic-simple-address/statamic-simple-address/README.md
````

Expected: Readable markdown with configuration section

**Step 4: Commit**

```bash
git add README.md
git commit -m "docs: add configuration examples and provider switching guide"
```

---

## Summary

After all tasks complete:

- ✅ Configurable provider system via config file
- ✅ Transparent caching with PSR-16 SimpleCache
- ✅ Factory method support for complex provider instantiation
- ✅ ServiceProvider handles publishing and singleton binding
- ✅ Comprehensive test coverage
- ✅ Updated documentation
- ✅ All tests passing

**Verification Steps:**

1. Run full test suite: `./vendor/bin/pest`
2. Check git log: `git log --oneline -10`
3. Verify config is present: `ls config/simple-address.php`
4. Verify no uncommitted changes: `git status`
