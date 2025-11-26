<?php

use ElSchneider\StatamicSimpleAddress\Data\SearchResponse;
use ElSchneider\StatamicSimpleAddress\Providers\NominatimProvider;

it('transforms nominatim response', function () {
    $provider = new NominatimProvider;
    $provider->setExcludeFields(['boundingbox', 'osm_id']);

    $rawResponse = [
        [
            'place_id' => 88066702,
            'osm_id' => 71525,
            'lat' => '48.8534951',
            'lon' => '2.3483915',
            'class' => 'boundary',
            'type' => 'administrative',
            'name' => 'Paris',
            'display_name' => 'Paris, Île-de-France, France',
            'address' => ['city' => 'Paris', 'state' => 'Île-de-France'],
            'namedetails' => ['name' => 'Paris'],
            'boundingbox' => ['48.8155755', '48.9021560', '2.2241220', '2.4697602'],
        ],
    ];

    $response = $provider->transformResponse($rawResponse);

    expect($response)->toBeInstanceOf(SearchResponse::class);
    expect($response->results)->toHaveCount(1);

    $result = $response->results[0]->toArray();

    expect($result['label'])->toBe('Paris, Île-de-France, France');
    expect($result['lat'])->toBe('48.8534951');
    expect($result['lon'])->toBe('2.3483915');
    expect($result['type'])->toBe('administrative');
    expect($result['name'])->toBe('Paris');
    expect($result['address'])->toBe(['city' => 'Paris', 'state' => 'Île-de-France']);

    // Verify excluded fields are not in additional
    expect($result['additional'])->not()->toHaveKey('boundingbox');
    expect($result['additional'])->not()->toHaveKey('osm_id');
});

it('handles multiple results', function () {
    $provider = new NominatimProvider;

    $rawResponse = [
        [
            'lat' => '48.8534951',
            'lon' => '2.3483915',
            'type' => 'administrative',
            'name' => 'Paris',
            'display_name' => 'Paris, France',
            'address' => [],
        ],
        [
            'lat' => '33.6617962',
            'lon' => '-95.5555130',
            'type' => 'administrative',
            'name' => 'Paris',
            'display_name' => 'Paris, Texas, USA',
            'address' => [],
        ],
    ];

    $response = $provider->transformResponse($rawResponse);

    expect($response->results)->toHaveCount(2);
});

it('builds search request with countries and language', function () {
    $provider = new NominatimProvider;

    $request = $provider->buildSearchRequest('Paris', [
        'countries' => ['fr', 'de'],
        'language' => 'en',
    ]);

    expect($request['url'])->toBe('https://nominatim.openstreetmap.org/search');
    expect($request['params']['q'])->toBe('Paris');
    expect($request['params']['countrycodes'])->toBe('fr,de');
    expect($request['params']['accept-language'])->toBe('en');
    expect($request['params']['format'])->toBe('json');
});

it('builds reverse request', function () {
    $provider = new NominatimProvider;

    $request = $provider->buildReverseRequest(48.8534951, 2.3483915, [
        'language' => 'de',
    ]);

    expect($request['url'])->toBe('https://nominatim.openstreetmap.org/reverse');
    expect($request['params']['lat'])->toBe(48.8534951);
    expect($request['params']['lon'])->toBe(2.3483915);
    expect($request['params']['accept-language'])->toBe('de');
});

it('wraps single result for reverse response', function () {
    $provider = new NominatimProvider;

    // Nominatim returns single object for reverse
    $rawResponse = [
        'lat' => '48.8534951',
        'lon' => '2.3483915',
        'type' => 'administrative',
        'name' => 'Paris',
        'display_name' => 'Paris, France',
        'address' => ['city' => 'Paris'],
    ];

    $response = $provider->transformReverseResponse($rawResponse);

    expect($response->results)->toHaveCount(1);
    expect($response->results[0]->label)->toBe('Paris, France');
});

it('does not require api key', function () {
    $provider = new NominatimProvider;

    expect($provider->requiresApiKey())->toBeFalse();
});
