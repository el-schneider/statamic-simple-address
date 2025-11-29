<?php

use ElSchneider\StatamicSimpleAddress\Services\GeocodingService;
use Geocoder\Provider\Nominatim\Nominatim;

beforeEach(function () {
    // Disable cache for testing
    config()->set('simple-address.cache.enabled', false);
});

it('can switch provider via config', function () {
    config()->set('simple-address.provider', 'nominatim');
    config()->set('simple-address.providers.nominatim', [
        'class' => Nominatim::class,
        'factory' => 'withOpenStreetMapServer',
        'args' => ['Test App'],
    ]);

    $service = new GeocodingService;

    expect($service)->toBeInstanceOf(GeocodingService::class);
});

it('invalid provider throws clear error', function () {
    config()->set('simple-address.provider', 'invalid_provider');
    config()->set('simple-address.providers', []);

    expect(fn () => new GeocodingService)
        ->toThrow(InvalidArgumentException::class, "Provider 'invalid_provider' is not configured in simple-address config.");
});
