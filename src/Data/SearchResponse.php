<?php

namespace ElSchneider\StatamicSimpleAddress\Data;

readonly class SearchResponse
{
    /**
     * @param AddressResult[] $results
     */
    public function __construct(
        public array $results = [],
    ) {}

    public function toArray(): array
    {
        return [
            'results' => array_map(
                fn(AddressResult $result) => $result->toArray(),
                $this->results
            ),
        ];
    }
}
