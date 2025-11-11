<?php

namespace ElSchneider\StatamicSimpleAddress\Transformers;

use ElSchneider\StatamicSimpleAddress\Data\SearchResponse;

class GoogleTransformer extends Transformer
{
    /**
     * @param  string[]  $excludeFields
     */
    public function __construct(private array $excludeFields = []) {}

    public function transform(array $rawResponse): SearchResponse
    {
        $results = array_map(
            fn (array $item) => $this->normalize(
                $this->mapGoogleResponse($item),
                $this->excludeFields
            ),
            $rawResponse['results'] ?? []
        );

        return new SearchResponse($results);
    }

    /**
     * Map Google's response structure to a normalized format
     */
    private function mapGoogleResponse(array $item): array
    {
        // Extract type from location_type
        $type = $item['geometry']['location_type'] ?? '';

        // Build address object from address_components
        $address = $this->buildAddressFromComponents($item['address_components'] ?? []);

        return array_merge($item, [
            'label' => $item['formatted_address'] ?? '',
            'lat' => (string) ($item['geometry']['location']['lat'] ?? ''),
            'lon' => (string) ($item['geometry']['location']['lng'] ?? ''),
            'type' => $type,
            'name' => $this->extractNameFromComponents($item['address_components'] ?? []),
            'address' => $address,
        ]);
    }

    /**
     * Build an address object from Google's address_components array
     */
    private function buildAddressFromComponents(array $components): array
    {
        $address = [];

        foreach ($components as $component) {
            // Use the first type as the key (Google components have multiple types)
            $type = $component['types'][0] ?? null;
            if ($type) {
                $address[$type] = [
                    'long_name' => $component['long_name'] ?? '',
                    'short_name' => $component['short_name'] ?? '',
                ];
            }
        }

        return $address;
    }

    /**
     * Extract the primary name from address components
     * Prioritize: locality > administrative_area_level_1 > country
     */
    private function extractNameFromComponents(array $components): string
    {
        $priorities = [
            'locality',
            'administrative_area_level_1',
            'country',
        ];

        foreach ($priorities as $priority) {
            foreach ($components as $component) {
                if (in_array($priority, $component['types'] ?? [])) {
                    return $component['long_name'] ?? '';
                }
            }
        }

        return '';
    }
}
