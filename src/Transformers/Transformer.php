<?php

namespace ElSchneider\StatamicSimpleAddress\Transformers;

use ElSchneider\StatamicSimpleAddress\Data\AddressResult;
use ElSchneider\StatamicSimpleAddress\Data\SearchResponse;

abstract class Transformer
{
    /**
     * Transform raw provider response to normalized SearchResponse
     */
    abstract public function transform(array $rawResponse): SearchResponse;

    /**
     * Normalize a single item from provider response
     *
     * @param array<string, mixed> $item Raw item from provider
     * @param string[] $excludeFields Fields to exclude from result
     */
    protected function normalize(array $item, array $excludeFields): AddressResult
    {
        $canonical = [
            'label' => $item['label'] ?? $item['display_name'] ?? '',
            'lat' => (string) ($item['lat'] ?? ''),
            'lon' => (string) ($item['lon'] ?? ''),
            'type' => $item['type'] ?? '',
            'name' => $item['name'] ?? '',
            'address' => $item['address'] ?? [],
        ];

        // Build additional with everything else except excluded fields and canonical keys
        $excludeSet = array_flip(array_merge(
            $excludeFields,
            ['label', 'display_name', 'lat', 'lon', 'type', 'name', 'address']
        ));

        $additional = array_filter(
            $item,
            fn($key) => !isset($excludeSet[$key]),
            ARRAY_FILTER_USE_KEY
        );

        return new AddressResult(
            label: $canonical['label'],
            lat: $canonical['lat'],
            lon: $canonical['lon'],
            type: $canonical['type'],
            name: $canonical['name'],
            address: $canonical['address'],
            additional: $additional,
        );
    }
}
