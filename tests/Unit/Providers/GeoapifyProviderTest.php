<?php

use ElSchneider\StatamicSimpleAddress\Data\SearchResponse;
use ElSchneider\StatamicSimpleAddress\Providers\GeoapifyProvider;

it('transforms geoapify response', function () {
    $provider = new GeoapifyProvider;

    $rawResponse = [
        'results' => [
            [
                'lat' => 48.8534951,
                'lon' => 2.3483915,
                'formatted' => 'Paris, France',
                'address_line1' => 'Paris',
                'address_line2' => 'France',
                'result_type' => 'city',
                'name' => 'Paris',
                'address' => ['city' => 'Paris', 'country' => 'France'],
            ],
        ],
    ];

    $response = $provider->transformResponse($rawResponse);

    expect($response)->toBeInstanceOf(SearchResponse::class);
    expect($response->results)->toHaveCount(1);

    $result = $response->results[0]->toArray();

    expect($result['label'])->toBe('Paris, France');
    expect($result['lat'])->toBe('48.8534951');
    expect($result['lon'])->toBe('2.3483915');
    expect($result['type'])->toBe('city');
});

it('handles empty results', function () {
    $provider = new GeoapifyProvider;

    $rawResponse = ['results' => []];

    $response = $provider->transformResponse($rawResponse);

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

    $request = $provider->buildReverseRequest(52.52, 13.405, [
        'language' => 'en',
    ]);

    expect($request['url'])->toBe('https://api.geoapify.com/v1/geocode/reverse');
    expect($request['params']['lat'])->toBe(52.52);
    expect($request['params']['lon'])->toBe(13.405);
    expect($request['params']['lang'])->toBe('en');
    expect($request['params']['apiKey'])->toBe('test-key');
});

it('requires api key', function () {
    $provider = new GeoapifyProvider;

    expect($provider->requiresApiKey())->toBeTrue();
});
