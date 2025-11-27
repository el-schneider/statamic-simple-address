<?php

use ElSchneider\StatamicSimpleAddress\Data\SearchResponse;
use ElSchneider\StatamicSimpleAddress\Providers\NominatimProvider;

it('transforms nominatim response', function () {
    $provider = new NominatimProvider;

    $rawResponse = [
        [
            'osm_id' => 71525,
            'lat' => '48.8534951',
            'lon' => '2.3483915',
            'name' => 'Paris',
            'display_name' => 'Paris, Île-de-France, France',
            'address' => [
                'city' => 'Paris',
                'state' => 'Île-de-France',
                'country' => 'France',
                'country_code' => 'fr',
                'road' => 'Rue de Rivoli',
                'house_number' => '100',
                'postcode' => '75001',
            ],
        ],
    ];

    $response = $provider->transformResponse($rawResponse);

    expect($response)->toBeInstanceOf(SearchResponse::class);
    expect($response->results)->toHaveCount(1);

    $result = $response->results[0]->toArray();

    expect($result['id'])->toBe('71525');
    expect($result['label'])->toBe('Paris, Île-de-France, France');
    expect($result['lat'])->toBe('48.8534951');
    expect($result['lon'])->toBe('2.3483915');
    expect($result['name'])->toBe('Paris');
    expect($result['city'])->toBe('Paris');
    expect($result['region'])->toBe('Île-de-France');
    expect($result['country'])->toBe('France');
    expect($result['countryCode'])->toBe('FR');
    expect($result['street'])->toBe('Rue de Rivoli');
    expect($result['houseNumber'])->toBe('100');
    expect($result['postcode'])->toBe('75001');
});

it('handles multiple results', function () {
    $provider = new NominatimProvider;

    $rawResponse = [
        ['lat' => '48.85', 'lon' => '2.34', 'display_name' => 'Paris, France', 'address' => []],
        ['lat' => '33.66', 'lon' => '-95.55', 'display_name' => 'Paris, Texas, USA', 'address' => []],
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
});

it('builds reverse request', function () {
    $provider = new NominatimProvider;

    $request = $provider->buildReverseRequest(48.8534951, 2.3483915, ['language' => 'de']);

    expect($request['url'])->toBe('https://nominatim.openstreetmap.org/reverse');
    expect($request['params']['lat'])->toBe(48.8534951);
    expect($request['params']['lon'])->toBe(2.3483915);
    expect($request['params']['accept-language'])->toBe('de');
});

it('wraps single result for reverse response', function () {
    $provider = new NominatimProvider;

    $rawResponse = [
        'lat' => '48.85',
        'lon' => '2.34',
        'display_name' => 'Paris, France',
        'address' => ['city' => 'Paris'],
    ];

    $response = $provider->transformReverseResponse($rawResponse);

    expect($response->results)->toHaveCount(1);
    expect($response->results[0]->label)->toBe('Paris, France');
});

it('does not require api key', function () {
    expect((new NominatimProvider)->requiresApiKey())->toBeFalse();
});

it('extracts city from town when city not present', function () {
    $provider = new NominatimProvider;

    $rawResponse = [
        ['lat' => '51.0', 'lon' => '7.0', 'display_name' => 'Small Town, Germany', 'address' => ['town' => 'Small Town']],
    ];

    $response = $provider->transformResponse($rawResponse);
    expect($response->results[0]->toArray()['city'])->toBe('Small Town');
});
