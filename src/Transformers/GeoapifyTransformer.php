<?php

namespace ElSchneider\StatamicSimpleAddress\Transformers;

use ElSchneider\StatamicSimpleAddress\Data\SearchResponse;

class GeoapifyTransformer extends Transformer
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
            fn(array $item) => $this->normalize(
                array_merge($item, [
                    'label' => $item['formatted'] ?? '',
                    'type' => $item['result_type'] ?? '',
                ]),
                $this->excludeFields
            ),
            $rawResponse['results'] ?? []
        );

        return new SearchResponse($results);
    }
}
