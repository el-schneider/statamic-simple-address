<?php

use ElSchneider\StatamicSimpleAddress\Data\SearchResponse;
use ElSchneider\StatamicSimpleAddress\Transformers\GeoapifyTransformer;

test('transforms geoapify response', function () {
    $transformer = new GeoapifyTransformer([]);

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

    $response = $transformer->transform($rawResponse);

    expect($response)->toBeInstanceOf(SearchResponse::class);
    expect($response->results)->toHaveCount(1);

    $result = $response->results[0]->toArray();

    expect($result['label'])->toBe('Paris, France');
    expect($result['lat'])->toBe('48.8534951');
    expect($result['lon'])->toBe('2.3483915');
    expect($result['type'])->toBe('city');
});

test('handles empty results', function () {
    $transformer = new GeoapifyTransformer([]);

    $rawResponse = ['results' => []];

    $response = $transformer->transform($rawResponse);

    expect($response->results)->toHaveCount(0);
});
