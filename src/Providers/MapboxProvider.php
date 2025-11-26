<?php

namespace ElSchneider\StatamicSimpleAddress\Providers;

use ElSchneider\StatamicSimpleAddress\Data\SearchResponse;

/**
 * Mapbox Geocoding API provider.
 *
 * @see https://docs.mapbox.com/api/search/geocoding/
 */
class MapboxProvider extends AbstractProvider
{
    protected string $baseUrl = 'https://api.mapbox.com/geocoding/v5/mapbox.places';

    protected int $minDebounceDelay = 0;

    protected array $defaultExcludeFields = [
        'id',
        'geometry',
        'properties',
        'relevance',
        'bbox',
    ];

    public function __construct(array $config = [])
    {
        $this->baseUrl = env('MAPBOX_BASE_URL', $this->baseUrl);
        $this->apiKey = env('MAPBOX_ACCESS_TOKEN');

        parent::__construct($config);
    }

    public function buildSearchRequest(string $query, array $options = []): array
    {
        // Mapbox uses the query in the URL path
        $url = $this->baseUrl.'/'.urlencode($query).'.json';

        $params = [
            'access_token' => $this->apiKey,
        ];

        if (! empty($options['countries'])) {
            $params['country'] = implode(',', array_map('strtolower', $options['countries']));
        }

        if (! empty($options['language'])) {
            $params['language'] = $options['language'];
        }

        return [
            'url' => $url,
            'params' => $params,
        ];
    }

    public function buildReverseRequest(float $lat, float $lon, array $options = []): array
    {
        // Mapbox reverse uses coordinates in the URL path
        $url = $this->baseUrl."/{$lon},{$lat}.json";

        $params = [
            'access_token' => $this->apiKey,
        ];

        if (! empty($options['language'])) {
            $params['language'] = $options['language'];
        }

        return [
            'url' => $url,
            'params' => $params,
        ];
    }

    public function transformResponse(array $rawResponse): SearchResponse
    {
        $items = $rawResponse['features'] ?? [];

        $results = array_map(
            fn (array $item) => $this->normalize($this->mapMapboxResponse($item)),
            $items
        );

        return new SearchResponse($results);
    }

    /**
     * Map Mapbox's GeoJSON response structure to normalized format.
     */
    private function mapMapboxResponse(array $item): array
    {
        $coords = $item['geometry']['coordinates'] ?? [null, null];
        $context = $this->parseContext($item['context'] ?? []);

        return [
            'label' => $item['place_name'] ?? '',
            'lat' => (string) ($coords[1] ?? ''),
            'lon' => (string) ($coords[0] ?? ''),
            'type' => $item['place_type'][0] ?? '',
            'name' => $item['text'] ?? '',
            'address' => $context,
        ];
    }

    /**
     * Parse Mapbox context array into address components.
     */
    private function parseContext(array $context): array
    {
        $address = [];

        foreach ($context as $item) {
            // Context items have IDs like "place.123", "region.456", etc.
            $type = explode('.', $item['id'] ?? '')[0];
            if ($type) {
                $address[$type] = $item['text'] ?? '';
            }
        }

        return $address;
    }
}
