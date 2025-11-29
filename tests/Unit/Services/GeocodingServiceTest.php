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
}
