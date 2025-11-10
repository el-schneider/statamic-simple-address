<?php

namespace ElSchneider\StatamicSimpleAddress\Tests\Unit\Transformers;

use ElSchneider\StatamicSimpleAddress\Data\SearchResponse;
use ElSchneider\StatamicSimpleAddress\Transformers\GeocodifyTransformer;
use PHPUnit\Framework\TestCase;

class GeocodifyTransformerTest extends TestCase
{
    public function test_transforms_geocodify_response()
    {
        $transformer = new GeocodifyTransformer([]);

        $rawResponse = [
            'response' => [
                'features' => [
                    [
                        'properties' => [
                            'label' => 'Paris, France',
                            'lat' => 48.8566,
                            'lon' => 2.3522,
                            'type' => 'city',
                            'name' => 'Paris',
                            'address' => ['city' => 'Paris', 'country' => 'France'],
                        ],
                    ],
                ],
            ],
        ];

        $response = $transformer->transform($rawResponse);

        $this->assertInstanceOf(SearchResponse::class, $response);
        $this->assertCount(1, $response->results);

        $result = $response->results[0]->toArray();

        $this->assertEquals('Paris, France', $result['label']);
        $this->assertEquals('48.8566', $result['lat']);
    }
}
