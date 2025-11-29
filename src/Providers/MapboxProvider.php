<?php

namespace ElSchneider\StatamicSimpleAddress\Providers;

use ElSchneider\StatamicSimpleAddress\Data\SearchResponse;

class MapboxProvider extends AbstractProvider
{
    protected string $baseUrl = 'https://api.mapbox.com/geocoding/v5/mapbox.places';

    protected int $minDebounceDelay = 0;

    public function __construct(array $config = [])
    {
        $this->baseUrl = env('MAPBOX_BASE_URL', $this->baseUrl);
        $this->apiKey = env('MAPBOX_ACCESS_TOKEN');

        parent::__construct($config);
    }

    public function buildSearchRequest(string $query, array $options = []): array
    {
        $url = $this->baseUrl.'/'.urlencode($query).'.json';
        $params = [
            'access_token' => $this->apiKey,
            'permanent' => 'true', // Required for storing geocoded data permanently per Mapbox ToS
        ];

        if (! empty($options['countries'])) {
            $params['country'] = implode(',', array_map('strtolower', $options['countries']));
        }

        if (! empty($options['language'])) {
            $params['language'] = $options['language'];
        }

        return ['url' => $url, 'params' => $params];
    }

    public function buildReverseRequest(float $lat, float $lon, array $options = []): array
    {
        $url = $this->baseUrl."/{$lon},{$lat}.json";
        $params = [
            'access_token' => $this->apiKey,
            'permanent' => 'true', // Required for storing geocoded data permanently per Mapbox ToS
        ];

        if (! empty($options['language'])) {
            $params['language'] = $options['language'];
        }

        return ['url' => $url, 'params' => $params];
    }

    public function transformResponse(array $rawResponse): SearchResponse
    {
        $items = $rawResponse['features'] ?? [];
        $results = array_map(fn ($item) => $this->mapItem($item), $items);

        return new SearchResponse($results);
    }

    protected function mapItem(array $item): \ElSchneider\StatamicSimpleAddress\Data\AddressResult
    {
        $coords = $item['geometry']['coordinates'] ?? [null, null];
        $c = $this->parseContext($item['context'] ?? []);

        return $this->createResult(
            label: $item['place_name'] ?? '',
            lat: (string) ($coords[1] ?? ''),
            lon: (string) ($coords[0] ?? ''),
            data: [
                'id' => $item['id'] ?? null,
                'name' => $item['text'] ?? null,
                'street' => $c['address'] ?? null,
                'houseNumber' => $item['address'] ?? null,
                'postcode' => $c['postcode'] ?? null,
                'city' => $c['place'] ?? null,
                'region' => $c['region'] ?? null,
                'country' => $c['country'] ?? null,
                'countryCode' => isset($c['country_code']) ? strtoupper($c['country_code']) : null,
            ],
        );
    }

    private function parseContext(array $context): array
    {
        $result = [];

        foreach ($context as $item) {
            $type = explode('.', $item['id'] ?? '')[0];
            if ($type) {
                $result[$type] = $item['text'] ?? '';
                if ($type === 'country' && isset($item['short_code'])) {
                    $result['country_code'] = $item['short_code'];
                }
            }
        }

        return $result;
    }
}
