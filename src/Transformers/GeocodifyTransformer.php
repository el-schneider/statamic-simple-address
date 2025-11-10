<?php

namespace ElSchneider\StatamicSimpleAddress\Transformers;

use ElSchneider\StatamicSimpleAddress\Data\SearchResponse;

class GeocodifyTransformer extends Transformer
{
    /**
     * @param string[] $excludeFields
     */
    public function __construct(private array $excludeFields = [])
    {
    }

    public function transform(array $rawResponse): SearchResponse
    {
        $features = $rawResponse['response']['features'] ?? [];

        $results = array_map(
            fn(array $feature) => $this->normalize(
                $feature['properties'] ?? [],
                $this->excludeFields
            ),
            $features
        );

        return new SearchResponse($results);
    }
}
