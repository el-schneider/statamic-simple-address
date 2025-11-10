<?php

namespace ElSchneider\StatamicSimpleAddress\Transformers;

use ElSchneider\StatamicSimpleAddress\Data\SearchResponse;

class NominatimTransformer extends Transformer
{
    /**
     * @param string[] $excludeFields
     */
    public function __construct(private array $excludeFields = [])
    {
    }

    public function transform(array $rawResponse): SearchResponse
    {
        $results = array_map(
            fn(array $item) => $this->normalize($item, $this->excludeFields),
            $rawResponse
        );

        return new SearchResponse($results);
    }
}
