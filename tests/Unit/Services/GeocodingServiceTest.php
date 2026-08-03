<?php

use ElSchneider\StatamicSimpleAddress\ServiceProvider;
use ElSchneider\StatamicSimpleAddress\Services\GeocodingService;

test('that an error is thrown if the provider is not configured', function () {
    config(['simple-address.provider' => 'nonexistent']);
    config(['simple-address.providers' => []]);

    expect(fn () => new GeocodingService)
        ->toThrow(InvalidArgumentException::class, "Provider 'nonexistent' is not configured");
});

test('that the service provider publishes config', function () {
    $provider = app()->getProvider(ServiceProvider::class);
    $publishables = $provider::$publishes;

    expect($publishables)->toHaveKey(ServiceProvider::class);
    $configPublishables = $publishables[ServiceProvider::class];

    $expectedSource = __DIR__.'/../../../config/simple-address.php';
    $expectedTarget = config_path('simple-address.php');

    $foundMatch = false;
    foreach ($configPublishables as $source => $target) {
        if (realpath($source) === realpath($expectedSource) && $target === $expectedTarget) {
            $foundMatch = true;
            break;
        }
    }

    expect($foundMatch)->toBeTrue();
});

test('that the geocoding service is bound as singleton', function () {
    $service1 = app()->make(GeocodingService::class);
    $service2 = app()->make(GeocodingService::class);

    expect($service1)->toBe($service2);
});
