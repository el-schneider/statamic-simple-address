<?php

use ElSchneider\StatamicSimpleAddress\ServiceProvider;
use ElSchneider\StatamicSimpleAddress\Services\GeocodingService;
use ElSchneider\StatamicSimpleAddress\Services\SerializableGeocoderCache;
use Geocoder\Model\AddressCollection;
use Geocoder\Query\GeocodeQuery;
use Tests\Stubs\GoogleLikeProvider;
use Tests\Stubs\RestrictedUnserializeCache;

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

test('geocoder cache stores scalar arrays that survive restricted unserialization', function () {
    GoogleLikeProvider::$calls = 0;
    $cache = new RestrictedUnserializeCache;
    $provider = new SerializableGeocoderCache(new GoogleLikeProvider, $cache, 3600);
    $query = GeocodeQuery::create('Holzmarkt 1, Tübingen');

    $first = $provider->geocodeQuery($query);
    $second = $provider->geocodeQuery($query);

    expect(GoogleLikeProvider::$calls)->toBe(1)
        ->and($first)->toBeInstanceOf(AddressCollection::class)
        ->and($second)->toBeInstanceOf(AddressCollection::class)
        ->and($second->first()->getProvidedBy())->toBe('google_maps')
        ->and($second->first()->getLocality())->toBe('Tübingen')
        ->and(implode('', $cache->items))->not->toContain('Geocoder\\Model');
});

test('geocoding service uses safe cache with google-like provider when object serialization is restricted', function () {
    GoogleLikeProvider::$calls = 0;

    config([
        'simple-address.provider' => 'google_like',
        'simple-address.providers.google_like' => [
            'class' => GoogleLikeProvider::class,
        ],
        'simple-address.cache.enabled' => true,
        'simple-address.cache.store' => 'restricted_unserialize',
        'cache.stores.restricted_unserialize' => ['driver' => 'restricted_unserialize'],
    ]);

    app('cache')->extend('restricted_unserialize', fn () => new RestrictedUnserializeCache);
    app()->forgetInstance(GeocodingService::class);

    $service = new GeocodingService;

    $first = $service->geocode(GeocodeQuery::create('Holzmarkt 1, Tübingen'));
    $second = $service->geocode(GeocodeQuery::create('Holzmarkt 1, Tübingen'));

    expect(GoogleLikeProvider::$calls)->toBe(1)
        ->and($first[0]->getProvidedBy())->toBe('google_maps')
        ->and($second[0]->getLocality())->toBe('Tübingen');
});
