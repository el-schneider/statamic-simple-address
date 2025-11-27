<?php

namespace ElSchneider\StatamicSimpleAddress\Providers;

use ElSchneider\StatamicSimpleAddress\Data\SearchResponse;

class GoogleProvider extends AbstractProvider
{
    protected string $baseUrl = 'https://maps.googleapis.com/maps/api/geocode/json';

    protected int $minDebounceDelay = 0;

    public function __construct(array $config = [])
    {
        $this->baseUrl = env('GOOGLE_GEOCODE_BASE_URL', $this->baseUrl);
        $this->apiKey = env('GOOGLE_GEOCODE_API_KEY');

        parent::__construct($config);
    }

    public function buildSearchRequest(string $query, array $options = []): array
    {
        $params = ['address' => $query];

        if (! empty($options['countries'])) {
            $params['region'] = strtolower($options['countries'][0]);
            $params['components'] = 'country:'.implode('|country:', array_map('strtoupper', $options['countries']));
        }

        if (! empty($options['language'])) {
            $params['language'] = $options['language'];
        }

        if ($this->apiKey) {
            $params['key'] = $this->apiKey;
        }

        return ['url' => $this->baseUrl, 'params' => $params];
    }

    public function buildReverseRequest(float $lat, float $lon, array $options = []): array
    {
        $params = ['latlng' => "{$lat},{$lon}"];

        if (! empty($options['language'])) {
            $params['language'] = $options['language'];
        }

        if ($this->apiKey) {
            $params['key'] = $this->apiKey;
        }

        return ['url' => $this->baseUrl, 'params' => $params];
    }

    public function transformResponse(array $rawResponse): SearchResponse
    {
        $items = $rawResponse['results'] ?? [];
        $results = array_map(fn ($item) => $this->mapItem($item), $items);

        return new SearchResponse($results);
    }

    protected function mapItem(array $item): \ElSchneider\StatamicSimpleAddress\Data\AddressResult
    {
        $c = $this->parseComponents($item['address_components'] ?? []);

        return $this->createResult(
            label: $item['formatted_address'] ?? '',
            lat: (string) ($item['geometry']['location']['lat'] ?? ''),
            lon: (string) ($item['geometry']['location']['lng'] ?? ''),
            data: [
                'id' => $item['place_id'] ?? null,
                'name' => $c['locality'] ?? $c['administrative_area_level_1'] ?? null,
                'street' => $c['route'] ?? null,
                'houseNumber' => $c['street_number'] ?? null,
                'postcode' => $c['postal_code'] ?? null,
                'city' => $c['locality'] ?? $c['postal_town'] ?? null,
                'region' => $c['administrative_area_level_1'] ?? null,
                'country' => $c['country'] ?? null,
                'countryCode' => $c['country_code'] ?? null,
            ],
        );
    }

    private function parseComponents(array $components): array
    {
        $result = [];

        foreach ($components as $component) {
            foreach ($component['types'] ?? [] as $type) {
                $result[$type] = $component['long_name'] ?? '';
                if ($type === 'country') {
                    $result['country_code'] = $component['short_name'] ?? null;
                }
            }
        }

        return $result;
    }
}
