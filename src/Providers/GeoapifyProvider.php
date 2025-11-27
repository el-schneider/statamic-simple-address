<?php

namespace ElSchneider\StatamicSimpleAddress\Providers;

use ElSchneider\StatamicSimpleAddress\Data\SearchResponse;

class GeoapifyProvider extends AbstractProvider
{
    protected string $baseUrl = 'https://api.geoapify.com/v1/geocode/search';

    protected ?string $reverseBaseUrl = 'https://api.geoapify.com/v1/geocode/reverse';

    protected int $minDebounceDelay = 0;

    public function __construct(array $config = [])
    {
        $this->baseUrl = env('GEOAPIFY_BASE_URL', $this->baseUrl);
        $this->reverseBaseUrl = env('GEOAPIFY_REVERSE_BASE_URL', $this->reverseBaseUrl);
        $this->apiKey = env('GEOAPIFY_API_KEY');

        parent::__construct($config);
    }

    public function buildSearchRequest(string $query, array $options = []): array
    {
        $params = ['text' => $query, 'format' => 'json'];

        if (! empty($options['countries'])) {
            $params['filter'] = 'countrycode:'.implode(',', array_map('strtolower', $options['countries']));
        }

        if (! empty($options['language'])) {
            $params['lang'] = $options['language'];
        }

        if ($this->apiKey) {
            $params['apiKey'] = $this->apiKey;
        }

        return ['url' => $this->baseUrl, 'params' => $params];
    }

    public function buildReverseRequest(float $lat, float $lon, array $options = []): array
    {
        $params = ['lat' => $lat, 'lon' => $lon, 'format' => 'json'];

        if (! empty($options['language'])) {
            $params['lang'] = $options['language'];
        }

        if ($this->apiKey) {
            $params['apiKey'] = $this->apiKey;
        }

        return ['url' => $this->getReverseBaseUrl(), 'params' => $params];
    }

    public function transformResponse(array $rawResponse): SearchResponse
    {
        $items = $rawResponse['results'] ?? [];
        $results = array_map(fn ($item) => $this->mapItem($item), $items);

        return new SearchResponse($results);
    }

    protected function mapItem(array $item): \ElSchneider\StatamicSimpleAddress\Data\AddressResult
    {
        return $this->createResult(
            label: $item['formatted'] ?? '',
            lat: (string) ($item['lat'] ?? ''),
            lon: (string) ($item['lon'] ?? ''),
            data: [
                'id' => $item['place_id'] ?? null,
                'name' => $item['name'] ?? null,
                'street' => $item['street'] ?? null,
                'houseNumber' => $item['housenumber'] ?? null,
                'postcode' => $item['postcode'] ?? null,
                'city' => $item['city'] ?? null,
                'region' => $item['state'] ?? null,
                'country' => $item['country'] ?? null,
                'countryCode' => isset($item['country_code']) ? strtoupper($item['country_code']) : null,
            ],
        );
    }
}
