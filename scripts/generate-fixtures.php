#!/usr/bin/env php
<?php

/**
 * Generate API response fixtures for geocoding providers.
 *
 * Usage:
 *   php scripts/generate-fixtures.php              # All providers
 *   php scripts/generate-fixtures.php google       # Only Google
 *   php scripts/generate-fixtures.php google nominatim  # Multiple providers
 *
 * Environment variables (set in .env or export):
 *   GOOGLE_GEOCODE_API_KEY - Google Geocoding API key
 *   GEOAPIFY_API_KEY       - Geoapify API key
 *   MAPBOX_ACCESS_TOKEN    - Mapbox API key
 *
 * The script saves raw API responses as JSON fixtures in tests/__fixtures__/providers/
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
        $this->geocoding = new GeocodingService;
    }

    public function run(array $argv): int
    {
        $providers = $this->resolveProviders(array_slice($argv, 1));

        echo "=== Generating API Fixtures ===\n";
        echo "Search query: {$this->searchQuery}\n";
        echo "Reverse coords: {$this->reverseLat}, {$this->reverseLon}\n";
        echo 'Providers: '.implode(', ', $providers)."\n";

        $success = 0;
        $errors = 0;

        foreach ($providers as $provider) {
            echo "\n## {$provider}\n";

            // Search fixture
            try {
                $this->generateSearchFixture($provider);
                $success++;
            } catch (Exception $e) {
                echo "   ✗ search: {$e->getMessage()}\n";
                $errors++;
            }

            // Reverse fixture
            try {
                $this->generateReverseFixture($provider);
                $success++;
            } catch (Exception $e) {
                echo "   ✗ reverse: {$e->getMessage()}\n";
                $errors++;
            }
        }

        echo "\n=== Summary ===\n";
        echo "✓ {$success} fixtures generated\n";

        if ($errors > 0) {
            echo "✗ {$errors} errors\n";

            return 1;
        }

        return 0;
    }

    /**
     * @return string[]
     */
    private function resolveProviders(array $args): array
    {
        $available = $this->geocoding->getAvailableProviders();

        if (empty($args)) {
            return $available;
        }

        $requested = array_map('strtolower', $args);
        $invalid = array_diff($requested, $available);

        if (! empty($invalid)) {
            echo 'Unknown providers: '.implode(', ', $invalid)."\n";
            echo 'Available: '.implode(', ', $available)."\n";
            exit(1);
        }

        return $requested;
    }

    private function generateSearchFixture(string $provider): void
    {
        $result = $this->geocoding->searchRaw($provider, $this->searchQuery);
        $filename = $this->saveFixture($provider, 'search', 'berlin_germany', $result['response']);

        echo "   ✓ {$filename}\n";
    }

    private function generateReverseFixture(string $provider): void
    {
        $result = $this->geocoding->reverseRaw($provider, $this->reverseLat, $this->reverseLon);
        $filename = $this->saveFixture($provider, 'reverse', 'berlin', $result['response']);

        echo "   ✓ {$filename}\n";
    }

    private function saveFixture(string $provider, string $type, string $name, array $response): string
    {
        $dir = "{$this->fixturesPath}/{$provider}";

        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $filename = "{$type}_{$name}.json";
        $filepath = "{$dir}/{$filename}";

        $prettyJson = json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        file_put_contents($filepath, $prettyJson."\n");

        return $filename;
    }
}

$generator = new FixtureGenerator;
exit($generator->run($argv));
