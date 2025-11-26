<?php

namespace ElSchneider\StatamicSimpleAddress\Providers;

use ElSchneider\StatamicSimpleAddress\Data\SearchResponse;

class GoogleProvider extends AbstractProvider
{
    protected string $baseUrl = 'https://maps.googleapis.com/maps/api/geocode/json';

    protected int $minDebounceDelay = 0;

    protected array $defaultExcludeFields = [
        'address_components',
        'geometry',
        'place_id',
    ];

    public function __construct(array $config = [])
    {
        // Set defaults from env before parent constructor
        $this->baseUrl = env('GOOGLE_GEOCODE_BASE_URL', $this->baseUrl);
        $this->apiKey = env('GOOGLE_GEOCODE_API_KEY');

        parent::__construct($config);
    }

    public function buildSearchRequest(string $query, array $options = []): array
    {
        $params = [
            'address' => $query,
        ];

        if (! empty($options['countries'])) {
            // Google uses region biasing with a single country code
            $params['region'] = strtolower($options['countries'][0]);
            // For strict filtering, use components
            $params['components'] = 'country:'.implode('|country:', array_map('strtoupper', $options['countries']));
        }

        if (! empty($options['language'])) {
            $params['language'] = $options['language'];
        }

        if ($this->apiKey) {
            $params['key'] = $this->apiKey;
        }

        return [
            'url' => $this->baseUrl,
            'params' => $params,
        ];
    }

    public function buildReverseRequest(float $lat, float $lon, array $options = []): array
    {
        $params = [
            'latlng' => "{$lat},{$lon}",
        ];

        if (! empty($options['language'])) {
            $params['language'] = $options['language'];
        }

        if ($this->apiKey) {
            $params['key'] = $this->apiKey;
        }

        return [
            'url' => $this->baseUrl, // Google uses same endpoint
            'params' => $params,
        ];
    }

    public function transformResponse(array $rawResponse): SearchResponse
    {
        $items = $rawResponse['results'] ?? [];

        $results = array_map(
            fn (array $item) => $this->normalize($this->mapGoogleResponse($item)),
            $items
        );

        return new SearchResponse($results);
    }

    /**
     * Map Google's response structure to normalized format.
     */
    private function mapGoogleResponse(array $item): array
    {
        $type = $item['geometry']['location_type'] ?? '';
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
     * Build an address object from Google's address_components array.
     */
    private function buildAddressFromComponents(array $components): array
    {
        $address = [];

        foreach ($components as $component) {
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
     * Extract the primary name from address components.
     * Prioritize: locality > administrative_area_level_1 > country
     */
    private function extractNameFromComponents(array $components): string
    {
        $priorities = ['locality', 'administrative_area_level_1', 'country'];

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
