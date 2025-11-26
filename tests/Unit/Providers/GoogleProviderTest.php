<?php

use ElSchneider\StatamicSimpleAddress\Data\SearchResponse;
use ElSchneider\StatamicSimpleAddress\Providers\GoogleProvider;

it('transforms google geocode response', function () {
    $provider = new GoogleProvider;

    $rawResponse = [
        'results' => [
            [
                'address_components' => [
                    [
                        'long_name' => '1600',
                        'short_name' => '1600',
                        'types' => ['street_number'],
                    ],
                    [
                        'long_name' => 'Amphitheatre Parkway',
                        'short_name' => 'Amphitheatre Pkwy',
                        'types' => ['route'],
                    ],
                    [
                        'long_name' => 'Mountain View',
                        'short_name' => 'Mountain View',
                        'types' => ['locality', 'political'],
                    ],
                    [
                        'long_name' => 'California',
                        'short_name' => 'CA',
                        'types' => ['administrative_area_level_1', 'political'],
                    ],
                    [
                        'long_name' => 'United States',
                        'short_name' => 'US',
                        'types' => ['country', 'political'],
                    ],
                ],
                'formatted_address' => '1600 Amphitheatre Pkwy, Mountain View, CA 94043, USA',
                'geometry' => [
                    'location' => [
                        'lat' => 37.4222804,
                        'lng' => -122.0843428,
                    ],
                    'location_type' => 'ROOFTOP',
                ],
                'place_id' => 'ChIJRxcAvRO7j4AR6hm6tys8yA8',
            ],
        ],
    ];

    $response = $provider->transformResponse($rawResponse);

    expect($response)->toBeInstanceOf(SearchResponse::class);
    expect($response->results)->toHaveCount(1);

    $result = $response->results[0]->toArray();

    expect($result['label'])->toBe('1600 Amphitheatre Pkwy, Mountain View, CA 94043, USA');
    expect($result['lat'])->toBe('37.4222804');
    expect($result['lon'])->toBe('-122.0843428');
    expect($result['type'])->toBe('ROOFTOP');
    expect($result['name'])->toBe('Mountain View');
});

it('extracts address components from google response', function () {
    $provider = new GoogleProvider;

    $rawResponse = [
        'results' => [
            [
                'address_components' => [
                    [
                        'long_name' => 'London',
                        'short_name' => 'London',
                        'types' => ['locality', 'political'],
                    ],
                    [
                        'long_name' => 'England',
                        'short_name' => 'England',
                        'types' => ['administrative_area_level_1', 'political'],
                    ],
                    [
                        'long_name' => 'United Kingdom',
                        'short_name' => 'UK',
                        'types' => ['country', 'political'],
                    ],
                ],
                'formatted_address' => 'London, England, United Kingdom',
                'geometry' => [
                    'location' => [
                        'lat' => 51.5073219,
                        'lng' => -0.1276474,
                    ],
                    'location_type' => 'APPROXIMATE',
                ],
                'place_id' => 'ChIJdd4hrwbX2EgRmSrV3Gyaour',
            ],
        ],
    ];

    $response = $provider->transformResponse($rawResponse);
    $result = $response->results[0]->toArray();

    expect($result['address'])->toHaveKey('locality');
    expect($result['address'])->toHaveKey('administrative_area_level_1');
    expect($result['address'])->toHaveKey('country');

    expect($result['address']['locality']['long_name'])->toBe('London');
    expect($result['address']['country']['short_name'])->toBe('UK');
});

it('excludes default fields from additional', function () {
    $provider = new GoogleProvider;

    $rawResponse = [
        'results' => [
            [
                'address_components' => [],
                'formatted_address' => 'Test Address',
                'geometry' => [
                    'location' => ['lat' => 0, 'lng' => 0],
                    'location_type' => 'ROOFTOP',
                ],
                'place_id' => 'ChIJRxcAvRO7j4AR6hm6tys8yA8',
                'custom_field' => 'custom_value',
            ],
        ],
    ];

    $response = $provider->transformResponse($rawResponse);
    $result = $response->results[0]->toArray();

    // place_id is in defaultExcludeFields
    expect($result['additional'])->not()->toHaveKey('place_id');
    expect($result['additional'])->toHaveKey('custom_field');
});

it('handles empty results', function () {
    $provider = new GoogleProvider;

    $rawResponse = ['results' => []];

    $response = $provider->transformResponse($rawResponse);

    expect($response->results)->toHaveCount(0);
});

it('handles missing address components', function () {
    $provider = new GoogleProvider;

    $rawResponse = [
        'results' => [
            [
                'formatted_address' => 'Simple Address',
                'geometry' => [
                    'location' => [
                        'lat' => 10.0,
                        'lng' => 20.0,
                    ],
                    'location_type' => 'GEOMETRIC_CENTER',
                ],
            ],
        ],
    ];

    $response = $provider->transformResponse($rawResponse);
    $result = $response->results[0]->toArray();

    expect($result['label'])->toBe('Simple Address');
    expect($result['address'])->toBe([]);
    expect($result['name'])->toBe('');
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

    $request = $provider->buildReverseRequest(51.5073, -0.1276, [
        'language' => 'de',
    ]);

    expect($request['params']['latlng'])->toBe('51.5073,-0.1276');
    expect($request['params']['language'])->toBe('de');
    expect($request['params']['key'])->toBe('test-key');
});
