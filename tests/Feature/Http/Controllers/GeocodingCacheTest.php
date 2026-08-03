<?php

use Geocoder\Provider\Nominatim\Model\NominatimAddress;
use Tests\Stubs\RestrictedUnserializeStore;
use Tests\Stubs\StubProvider;

beforeEach(function () {
    StubProvider::$calls = 0;
    StubProvider::$result = NominatimAddress::createFromArray([
        'providedBy' => 'nominatim', 'latitude' => 45.97, 'longitude' => 7.65, 'country' => 'Switzerland',
    ])->withDisplayName('Matterhorn, Zermatt, Valais, Switzerland');

    $store = $this->store = new RestrictedUnserializeStore;
    config(['simple-address.cache.store' => 'restricted', 'cache.stores.restricted' => ['driver' => 'restricted']]);
    // extend() rebinds the closure to the CacheManager, so the store has to be captured.
    app('cache')->extend('restricted', fn ($app) => $app['cache']->repository($store));

    $this->actingAsSuperAdmin();
});

afterEach(fn () => StubProvider::$result = null);

test('that a cached response is identical to a fresh one', function () {
    $fresh = $this->post('/cp/simple-address/search', ['query' => 'Matterhorn'])->json('results');
    $cached = $this->post('/cp/simple-address/search', ['query' => 'Matterhorn'])->json('results');

    expect(StubProvider::$calls)->toBe(1)
        ->and($cached)->toBe($fresh)
        ->and($cached[0]['label'])->toBe('Matterhorn, Zermatt, Valais, Switzerland')
        ->and($this->store->serialized())->not->toContain('Geocoder\\');
});

test('that a cached reverse response is identical to a fresh one', function () {
    $body = ['lat' => 45.9764263, 'lon' => 7.6586024];

    $fresh = $this->post('/cp/simple-address/reverse', $body)->json('results');
    $cached = $this->post('/cp/simple-address/reverse', $body)->json('results');

    expect(StubProvider::$calls)->toBe(1)
        ->and($cached)->toBe($fresh)
        ->and($cached[0]['label'])->toBe('Matterhorn, Zermatt, Valais, Switzerland');
});

test('that search and reverse do not share an entry', function () {
    $body = ['query' => 'Matterhorn', 'lat' => 45.9764263, 'lon' => 7.6586024];

    $this->post('/cp/simple-address/search', $body);
    $this->post('/cp/simple-address/reverse', $body);

    expect(StubProvider::$calls)->toBe(2);
});

test('that a different question is a different cache entry', function () {
    $this->post('/cp/simple-address/search', ['query' => 'Matterhorn']);
    $this->post('/cp/simple-address/search', ['query' => 'Matterhorn', 'countries' => ['ch']]);
    $this->post('/cp/simple-address/search', ['query' => 'Matterhorn', 'language' => 'de']);
    $this->post('/cp/simple-address/search', ['query' => 'Matterhorn', 'exclude_fields' => ['bounds']]);

    expect(StubProvider::$calls)->toBe(4);
});

test('that caching can be turned off', function () {
    config(['simple-address.cache.enabled' => false]);

    $this->post('/cp/simple-address/search', ['query' => 'Matterhorn']);
    $this->post('/cp/simple-address/search', ['query' => 'Matterhorn']);

    expect(StubProvider::$calls)->toBe(2);
});
