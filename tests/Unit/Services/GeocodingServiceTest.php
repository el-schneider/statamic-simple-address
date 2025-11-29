<?php

namespace Tests\Unit\Services;

use ElSchneider\StatamicSimpleAddress\Services\GeocodingService;
use Geocoder\Provider\Nominatim\Nominatim;
use Tests\TestCase;

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
        $service = new GeocodingService;

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

        new GeocodingService;
    }

    public function test_service_provider_publishes_config()
    {
        // Arrange: Get the service provider
        $provider = $this->app->getProvider(\ElSchneider\StatamicSimpleAddress\ServiceProvider::class);

        // Act: Get publishable groups
        $publishables = $provider::$publishes;

        // Assert: Config file should be publishable
        $this->assertArrayHasKey(\ElSchneider\StatamicSimpleAddress\ServiceProvider::class, $publishables);
        $configPublishables = $publishables[\ElSchneider\StatamicSimpleAddress\ServiceProvider::class];

        $expectedSource = __DIR__.'/../../../config/simple-address.php';
        $expectedTarget = config_path('simple-address.php');

        $foundMatch = false;
        foreach ($configPublishables as $source => $target) {
            if (realpath($source) === realpath($expectedSource) && $target === $expectedTarget) {
                $foundMatch = true;
                break;
            }
        }

        $this->assertTrue($foundMatch, 'Config file not found in publishables');
    }

    public function test_geocoding_service_is_bound_as_singleton()
    {
        // Arrange: Configure Nominatim
        config(['simple-address.provider' => 'nominatim']);
        config(['simple-address.cache.enabled' => false]);
        config(['simple-address.providers.nominatim' => [
            'class' => Nominatim::class,
            'factory' => 'withOpenStreetMapServer',
            'args' => ['Test App'],
        ]]);

        // Act: Resolve service twice
        $service1 = $this->app->make(GeocodingService::class);
        $service2 = $this->app->make(GeocodingService::class);

        // Assert: Should be the same instance
        $this->assertSame($service1, $service2);
    }
}
