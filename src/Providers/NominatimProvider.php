<?php

namespace ElSchneider\StatamicSimpleAddress\Providers;

use ElSchneider\StatamicSimpleAddress\Data\SearchResponse;

class NominatimProvider extends AbstractProvider
{
    protected string $baseUrl = 'https://nominatim.openstreetmap.org/search';

    protected ?string $reverseBaseUrl = 'https://nominatim.openstreetmap.org/reverse';

    protected int $minDebounceDelay = 1000;

    public function __construct(array $config = [])
    {
        $this->baseUrl = env('NOMINATIM_BASE_URL', $this->baseUrl);
        $this->reverseBaseUrl = env('NOMINATIM_REVERSE_BASE_URL', $this->reverseBaseUrl);

        parent::__construct($config);
    }

    public function requiresApiKey(): bool
    {
        return false;
    }

    public function buildSearchRequest(string $query, array $options = []): array
    {
        $params = [
            'q' => $query,
            'addressdetails' => '1',
            'format' => 'json',
        ];

        if (! empty($options['countries'])) {
            $params['countrycodes'] = implode(',', $options['countries']);
        }

        if (! empty($options['language'])) {
            $params['accept-language'] = $options['language'];
        }

        return ['url' => $this->baseUrl, 'params' => $params];
    }

    public function buildReverseRequest(float $lat, float $lon, array $options = []): array
    {
        $params = [
            'lat' => $lat,
            'lon' => $lon,
            'format' => 'json',
            'addressdetails' => '1',
            'zoom' => '18',
        ];

        if (! empty($options['language'])) {
            $params['accept-language'] = $options['language'];
        }

        return ['url' => $this->getReverseBaseUrl(), 'params' => $params];
    }

    public function transformResponse(array $rawResponse): SearchResponse
    {
        $results = array_map(fn ($item) => $this->mapItem($item), $rawResponse);

        return new SearchResponse($results);
    }

    public function transformReverseResponse(array $rawResponse): SearchResponse
    {
        if (isset($rawResponse['lat']) && ! isset($rawResponse[0])) {
            $rawResponse = [$rawResponse];
        }

        return $this->transformResponse($rawResponse);
    }

    protected function mapItem(array $item): \ElSchneider\StatamicSimpleAddress\Data\AddressResult
    {
        $addr = $item['address'] ?? [];

        return $this->createResult(
            label: $item['display_name'] ?? '',
            lat: (string) ($item['lat'] ?? ''),
            lon: (string) ($item['lon'] ?? ''),
            data: [
                'id' => isset($item['osm_id']) ? (string) $item['osm_id'] : null,
                'name' => $item['name'] ?? null,
                'street' => $addr['road'] ?? null,
                'houseNumber' => $addr['house_number'] ?? null,
                'postcode' => $addr['postcode'] ?? null,
                'city' => $addr['city'] ?? $addr['town'] ?? $addr['village'] ?? $addr['municipality'] ?? null,
                'region' => $addr['state'] ?? null,
                'country' => $addr['country'] ?? null,
                'countryCode' => isset($addr['country_code']) ? strtoupper($addr['country_code']) : null,
            ],
        );
    }
}
