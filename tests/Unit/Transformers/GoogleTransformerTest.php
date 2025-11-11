<?php

use ElSchneider\StatamicSimpleAddress\Data\SearchResponse;
use ElSchneider\StatamicSimpleAddress\Transformers\GoogleTransformer;

test('transforms google geocode response', function () {
    $transformer = new GoogleTransformer([]);

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

    $response = $transformer->transform($rawResponse);

    expect($response)->toBeInstanceOf(SearchResponse::class);
    expect($response->results)->toHaveCount(1);

    $result = $response->results[0]->toArray();

    expect($result['label'])->toBe('1600 Amphitheatre Pkwy, Mountain View, CA 94043, USA');
    expect($result['lat'])->toBe('37.4222804');
    expect($result['lon'])->toBe('-122.0843428');
    expect($result['type'])->toBe('ROOFTOP');
    expect($result['name'])->toBe('Mountain View');
});

test('extracts address components from google response', function () {
    $transformer = new GoogleTransformer([]);

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

    $response = $transformer->transform($rawResponse);
    $result = $response->results[0]->toArray();

    // Check address has expected structure
    expect($result['address'])->toHaveKey('locality');
    expect($result['address'])->toHaveKey('administrative_area_level_1');
    expect($result['address'])->toHaveKey('country');

    expect($result['address']['locality']['long_name'])->toBe('London');
    expect($result['address']['country']['short_name'])->toBe('UK');
});

test('excludes specified fields from additional', function () {
    $transformer = new GoogleTransformer(['place_id']);

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

    $response = $transformer->transform($rawResponse);
    $result = $response->results[0]->toArray();

    expect($result['additional'])->not()->toHaveKey('place_id');
    expect($result['additional'])->toHaveKey('custom_field');
});

test('handles empty results', function () {
    $transformer = new GoogleTransformer([]);

    $rawResponse = ['results' => []];

    $response = $transformer->transform($rawResponse);

    expect($response->results)->toHaveCount(0);
});

test('handles missing address components', function () {
    $transformer = new GoogleTransformer([]);

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

    $response = $transformer->transform($rawResponse);
    $result = $response->results[0]->toArray();

    expect($result['label'])->toBe('Simple Address');
    expect($result['address'])->toBe([]);
    expect($result['name'])->toBe('');
});
