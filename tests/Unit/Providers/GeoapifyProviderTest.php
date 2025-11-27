<?php

use ElSchneider\StatamicSimpleAddress\Data\SearchResponse;
use ElSchneider\StatamicSimpleAddress\Providers\GeoapifyProvider;

it('transforms geoapify response', function () {
    $provider = new GeoapifyProvider;

    $rawResponse = [
        'results' => [
            [
                'place_id' => 'abc123',
                'lat' => 48.8534951,
                'lon' => 2.3483915,
                'formatted' => '100 Rue de Rivoli, 75001 Paris, France',
                'name' => 'Rue de Rivoli',
                'city' => 'Paris',
                'state' => 'Île-de-France',
                'country' => 'France',
                'country_code' => 'fr',
                'street' => 'Rue de Rivoli',
                'housenumber' => '100',
                'postcode' => '75001',
            ],
        ],
    ];

    $response = $provider->transformResponse($rawResponse);

    expect($response)->toBeInstanceOf(SearchResponse::class);
    expect($response->results)->toHaveCount(1);

    $result = $response->results[0]->toArray();

    expect($result['id'])->toBe('abc123');
    expect($result['label'])->toBe('100 Rue de Rivoli, 75001 Paris, France');
    expect($result['city'])->toBe('Paris');
    expect($result['region'])->toBe('Île-de-France');
    expect($result['countryCode'])->toBe('FR');
    expect($result['street'])->toBe('Rue de Rivoli');
    expect($result['houseNumber'])->toBe('100');
    expect($result['postcode'])->toBe('75001');
});

it('handles empty results', function () {
    $provider = new GeoapifyProvider;
    $response = $provider->transformResponse(['results' => []]);
    expect($response->results)->toHaveCount(0);
});

it('builds search request with countries filter', function () {
    $provider = new GeoapifyProvider(['api_key' => 'test-key']);

    $request = $provider->buildSearchRequest('Berlin', [
        'countries' => ['DE', 'AT'],
        'language' => 'de',
    ]);

    expect($request['url'])->toBe('https://api.geoapify.com/v1/geocode/search');
    expect($request['params']['text'])->toBe('Berlin');
    expect($request['params']['filter'])->toBe('countrycode:de,at');
    expect($request['params']['lang'])->toBe('de');
    expect($request['params']['apiKey'])->toBe('test-key');
});

it('builds reverse request', function () {
    $provider = new GeoapifyProvider(['api_key' => 'test-key']);

    $request = $provider->buildReverseRequest(52.52, 13.405, ['language' => 'en']);

    expect($request['url'])->toBe('https://api.geoapify.com/v1/geocode/reverse');
    expect($request['params']['lat'])->toBe(52.52);
    expect($request['params']['lon'])->toBe(13.405);
    expect($request['params']['apiKey'])->toBe('test-key');
});

it('requires api key', function () {
    expect((new GeoapifyProvider)->requiresApiKey())->toBeTrue();
});
