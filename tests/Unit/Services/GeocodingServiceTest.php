<?php

use ElSchneider\StatamicSimpleAddress\ServiceProvider;
use ElSchneider\StatamicSimpleAddress\Services\GeocodingService;
use Geocoder\Model\Coordinates;
use Geocoder\Query\GeocodeQuery;
use Geocoder\Query\ReverseQuery;
use Tests\Stubs\NominatimProvider;
use Tests\Stubs\RestrictedUnserializeStore;

/**
 * Point the service at one Nominatim result, cached through a store that only hands
 * back plain data — Laravel 13's `serializable_classes = false`.
 */
function serviceWithRestrictedCache(): array
{
    NominatimProvider::$calls = 0;
    NominatimProvider::$result = NominatimProvider::address(
        ['latitude' => 45.9764263, 'longitude' => 7.6586024, 'locality' => 'Zermatt',
            'adminLevels' => [['level' => 1, 'name' => 'Wallis']], 'country' => 'Schweiz'],
        'Matterhorn, Zermatt, Visp, Wallis, 3920, Schweiz',
    );

    $store = new RestrictedUnserializeStore;

    config([
        'simple-address.provider' => 'nominatim_fixture',
        'simple-address.providers.nominatim_fixture' => ['class' => NominatimProvider::class],
        'simple-address.cache.enabled' => true,
        'simple-address.cache.store' => 'restricted_unserialize',
        'cache.stores.restricted_unserialize' => ['driver' => 'restricted_unserialize'],
    ]);

    app('cache')->extend('restricted_unserialize', fn ($app) => $app['cache']->repository($store));
    app('cache')->forgetDriver('restricted_unserialize');
    app()->forgetInstance(GeocodingService::class);

    return [new GeocodingService, $store];
}

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

test('that a cached result is identical to a fresh one', function () {
    [$service, $store] = serviceWithRestrictedCache();

    $fresh = $service->geocode(GeocodeQuery::create('Matterhorn'));
    $cached = $service->geocode(GeocodeQuery::create('Matterhorn'));

    expect(NominatimProvider::$calls)->toBe(1)
        ->and($cached)->toBe($fresh)
        ->and($cached[0]['label'])->toBe('Matterhorn, Zermatt, Wallis, Schweiz')
        ->and($store->serialized())->not->toContain('Geocoder\\');
});

test('that a cached reverse result is identical to a fresh one', function () {
    [$service, $store] = serviceWithRestrictedCache();

    $query = ReverseQuery::create(new Coordinates(45.9764263, 7.6586024));
    $fresh = $service->reverse($query);
    $cached = $service->reverse($query);

    expect(NominatimProvider::$calls)->toBe(1)
        ->and($cached)->toBe($fresh)
        ->and($cached[0]['label'])->toBe('Matterhorn, Zermatt, Wallis, Schweiz')
        ->and($store->serialized())->not->toContain('Geocoder\\');
});

test('that the cache key covers the locale the provider is actually asked for', function () {
    [$service, $store] = serviceWithRestrictedCache();

    // No locale set, so the service default applies. An explicit default hits the same
    // entry, and so does an empty one, which the geocoder treats as unset. A different
    // language must not.
    $service->geocode(GeocodeQuery::create('Matterhorn'));
    $service->geocode(GeocodeQuery::create('Matterhorn')->withLocale('en'));
    $service->geocode(GeocodeQuery::create('Matterhorn')->withLocale(''));
    expect(NominatimProvider::$calls)->toBe(1);

    $service->geocode(GeocodeQuery::create('Matterhorn')->withLocale('de'));
    expect(NominatimProvider::$calls)->toBe(2)
        ->and($store->all())->toHaveCount(2);
});

test('that the cache key covers the country filter', function () {
    [$service, $store] = serviceWithRestrictedCache();

    $service->geocode(GeocodeQuery::create('Berlin'));
    $service->geocode(GeocodeQuery::create('Berlin')->withData('countrycodes', ['de']));
    $service->geocode(GeocodeQuery::create('Berlin')->withData('countrycodes', ['us']));

    expect(NominatimProvider::$calls)->toBe(3)
        ->and($store->all())->toHaveCount(3);
});

test('that switching provider does not serve the previous provider results', function () {
    [$service, $store] = serviceWithRestrictedCache();
    $service->geocode(GeocodeQuery::create('Matterhorn'));

    config(['simple-address.provider' => 'other_fixture']);
    config(['simple-address.providers.other_fixture' => ['class' => NominatimProvider::class]]);
    (new GeocodingService)->geocode(GeocodeQuery::create('Matterhorn'));

    expect(NominatimProvider::$calls)->toBe(2)
        ->and($store->all())->toHaveCount(2);
});
