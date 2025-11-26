<?php

namespace ElSchneider\StatamicSimpleAddress\Providers;

use ElSchneider\StatamicSimpleAddress\Data\SearchResponse;

class GeoapifyProvider extends AbstractProvider
{
    protected string $baseUrl = 'https://api.geoapify.com/v1/geocode/search';

    protected ?string $reverseBaseUrl = 'https://api.geoapify.com/v1/geocode/reverse';

    protected int $minDebounceDelay = 0;

    protected array $defaultExcludeFields = [
        'bbox',
        'geometry',
        'place_id',
        'query',
        'rank',
        'namedetails',
    ];

    public function __construct(array $config = [])
    {
        // Set defaults from env before parent constructor
        $this->baseUrl = env('GEOAPIFY_BASE_URL', $this->baseUrl);
        $this->reverseBaseUrl = env('GEOAPIFY_REVERSE_BASE_URL', $this->reverseBaseUrl);
        $this->apiKey = env('GEOAPIFY_API_KEY');

        parent::__construct($config);
    }

    public function buildSearchRequest(string $query, array $options = []): array
    {
        $params = [
            'text' => $query,
            'format' => 'json',
        ];

        if (! empty($options['countries'])) {
            $params['filter'] = 'countrycode:'.implode(',', array_map('strtolower', $options['countries']));
        }

        if (! empty($options['language'])) {
            $params['lang'] = $options['language'];
        }

        if ($this->apiKey) {
            $params['apiKey'] = $this->apiKey;
        }

        return [
            'url' => $this->baseUrl,
            'params' => $params,
        ];
    }

    public function buildReverseRequest(float $lat, float $lon, array $options = []): array
    {
        $params = [
            'lat' => $lat,
            'lon' => $lon,
            'format' => 'json',
        ];

        if (! empty($options['language'])) {
            $params['lang'] = $options['language'];
        }

        if ($this->apiKey) {
            $params['apiKey'] = $this->apiKey;
        }

        return [
            'url' => $this->getReverseBaseUrl(),
            'params' => $params,
        ];
    }

    public function transformResponse(array $rawResponse): SearchResponse
    {
        $items = $rawResponse['results'] ?? [];

        $results = array_map(
            fn (array $item) => $this->normalize(array_merge($item, [
                'label' => $item['formatted'] ?? '',
                'type' => $item['result_type'] ?? '',
            ])),
            $items
        );

        return new SearchResponse($results);
    }
}
