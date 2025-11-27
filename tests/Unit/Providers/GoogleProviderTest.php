<?php

use ElSchneider\StatamicSimpleAddress\Data\SearchResponse;
use ElSchneider\StatamicSimpleAddress\Providers\GoogleProvider;

it('transforms google geocode response', function () {
    $provider = new GoogleProvider;

    $rawResponse = [
        'results' => [
            [
                'place_id' => 'ChIJRxcAvRO7j4AR6hm6tys8yA8',
                'address_components' => [
                    ['long_name' => '1600', 'short_name' => '1600', 'types' => ['street_number']],
                    ['long_name' => 'Amphitheatre Parkway', 'short_name' => 'Amphitheatre Pkwy', 'types' => ['route']],
                    ['long_name' => 'Mountain View', 'short_name' => 'Mountain View', 'types' => ['locality', 'political']],
                    ['long_name' => 'California', 'short_name' => 'CA', 'types' => ['administrative_area_level_1', 'political']],
                    ['long_name' => 'United States', 'short_name' => 'US', 'types' => ['country', 'political']],
                    ['long_name' => '94043', 'short_name' => '94043', 'types' => ['postal_code']],
                ],
                'formatted_address' => '1600 Amphitheatre Pkwy, Mountain View, CA 94043, USA',
                'geometry' => ['location' => ['lat' => 37.4222804, 'lng' => -122.0843428]],
            ],
        ],
    ];

    $response = $provider->transformResponse($rawResponse);

    expect($response)->toBeInstanceOf(SearchResponse::class);
    expect($response->results)->toHaveCount(1);

    $result = $response->results[0]->toArray();

    expect($result['id'])->toBe('ChIJRxcAvRO7j4AR6hm6tys8yA8');
    expect($result['label'])->toBe('1600 Amphitheatre Pkwy, Mountain View, CA 94043, USA');
    expect($result['lat'])->toBe('37.4222804');
    expect($result['lon'])->toBe('-122.0843428');
    expect($result['city'])->toBe('Mountain View');
    expect($result['region'])->toBe('California');
    expect($result['country'])->toBe('United States');
    expect($result['countryCode'])->toBe('US');
    expect($result['street'])->toBe('Amphitheatre Parkway');
    expect($result['houseNumber'])->toBe('1600');
    expect($result['postcode'])->toBe('94043');
});

it('handles empty results', function () {
    $provider = new GoogleProvider;
    $response = $provider->transformResponse(['results' => []]);
    expect($response->results)->toHaveCount(0);
});

it('handles missing address components', function () {
    $provider = new GoogleProvider;

    $rawResponse = [
        'results' => [
            [
                'formatted_address' => 'Simple Address',
                'geometry' => ['location' => ['lat' => 10.0, 'lng' => 20.0]],
            ],
        ],
    ];

    $response = $provider->transformResponse($rawResponse);
    $result = $response->results[0]->toArray();

    expect($result['label'])->toBe('Simple Address');
    expect($result)->not->toHaveKey('city');
    expect($result)->not->toHaveKey('region');
});

it('builds search request with countries', function () {
    $provider = new GoogleProvider(['api_key' => 'test-key']);

    $request = $provider->buildSearchRequest('London', [
        'countries' => ['gb', 'us'],
        'language' => 'en',
    ]);

    expect($request['url'])->toBe('https://maps.googleapis.com/maps/api/geocode/json');
    expect($request['params']['address'])->toBe('London');
    expect($request['params']['components'])->toBe('country:GB|country:US');
    expect($request['params']['language'])->toBe('en');
    expect($request['params']['key'])->toBe('test-key');
});

it('builds reverse request', function () {
    $provider = new GoogleProvider(['api_key' => 'test-key']);

    $request = $provider->buildReverseRequest(51.5073, -0.1276, ['language' => 'de']);

    expect($request['params']['latlng'])->toBe('51.5073,-0.1276');
    expect($request['params']['language'])->toBe('de');
    expect($request['params']['key'])->toBe('test-key');
});
