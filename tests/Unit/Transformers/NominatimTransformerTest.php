<?php

namespace ElSchneider\StatamicSimpleAddress\Tests\Unit\Transformers;

use ElSchneider\StatamicSimpleAddress\Data\SearchResponse;
use ElSchneider\StatamicSimpleAddress\Transformers\NominatimTransformer;
use PHPUnit\Framework\TestCase;

class NominatimTransformerTest extends TestCase
{
    public function test_transforms_nominatim_response()
    {
        $transformer = new NominatimTransformer(['boundingbox', 'osm_id']);

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

        $response = $transformer->transform($rawResponse);

        $this->assertInstanceOf(SearchResponse::class, $response);
        $this->assertCount(1, $response->results);

        $result = $response->results[0]->toArray();

        $this->assertEquals('Paris, Île-de-France, France', $result['label']);
        $this->assertEquals('48.8534951', $result['lat']);
        $this->assertEquals('2.3483915', $result['lon']);
        $this->assertEquals('administrative', $result['type']);
        $this->assertEquals('Paris', $result['name']);
        $this->assertEquals(['city' => 'Paris', 'state' => 'Île-de-France'], $result['address']);

        // Verify excluded fields are not present
        $this->assertArrayNotHasKey('boundingbox', $result);
        $this->assertArrayNotHasKey('osm_id', $result);

        // Verify other fields are in additional
        $this->assertArrayHasKey('place_id', $result['additional']);
        $this->assertArrayHasKey('namedetails', $result['additional']);
    }

    public function test_handles_multiple_results()
    {
        $transformer = new NominatimTransformer([]);

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

        $response = $transformer->transform($rawResponse);

        $this->assertCount(2, $response->results);
    }
}
