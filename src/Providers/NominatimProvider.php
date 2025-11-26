<?php

namespace ElSchneider\StatamicSimpleAddress\Providers;

use ElSchneider\StatamicSimpleAddress\Data\SearchResponse;

class NominatimProvider extends AbstractProvider
{
    protected string $baseUrl = 'https://nominatim.openstreetmap.org/search';

    protected ?string $reverseBaseUrl = 'https://nominatim.openstreetmap.org/reverse';

    protected int $minDebounceDelay = 1000;

    protected array $defaultExcludeFields = [
        'boundingbox',
        'bbox',
        'class',
        'datasource',
        'display_name',
        'icon',
        'importance',
        'licence',
        'osm_id',
        'osm_type',
        'other_names',
        'place_id',
        'rank',
    ];

    public function __construct(array $config = [])
    {
        // Set defaults from env before parent constructor
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
            'namedetails' => '1',
            'format' => 'json',
        ];

        if (! empty($options['countries'])) {
            $params['countrycodes'] = implode(',', $options['countries']);
        }

        if (! empty($options['language'])) {
            $params['accept-language'] = $options['language'];
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
            'addressdetails' => '1',
            'zoom' => '18',
        ];

        if (! empty($options['language'])) {
            $params['accept-language'] = $options['language'];
        }

        return [
            'url' => $this->getReverseBaseUrl(),
            'params' => $params,
        ];
    }

    public function transformResponse(array $rawResponse): SearchResponse
    {
        $results = array_map(
            fn (array $item) => $this->normalize($item),
            $rawResponse
        );

        return new SearchResponse($results);
    }

    public function transformReverseResponse(array $rawResponse): SearchResponse
    {
        // Nominatim reverse returns a single object, wrap for consistency
        if (isset($rawResponse['lat']) && ! isset($rawResponse[0])) {
            $rawResponse = [$rawResponse];
        }

        return $this->transformResponse($rawResponse);
    }
}
