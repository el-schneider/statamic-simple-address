<?php

namespace ElSchneider\StatamicSimpleAddress\Providers;

use ElSchneider\StatamicSimpleAddress\Data\AddressResult;
use ElSchneider\StatamicSimpleAddress\Data\SearchResponse;

abstract class AbstractProvider
{
    protected string $baseUrl;

    protected ?string $reverseBaseUrl = null;

    protected ?string $apiKey = null;

    protected int $minDebounceDelay = 0;

    /** @var string[] Fields to exclude from response by default */
    protected array $defaultExcludeFields = [];

    /** @var string[] Runtime exclusion fields */
    protected array $excludeFields = [];

    public function __construct(array $config = [])
    {
        if (isset($config['base_url'])) {
            $this->baseUrl = $config['base_url'];
        }
        if (isset($config['reverse_base_url'])) {
            $this->reverseBaseUrl = $config['reverse_base_url'];
        }
        if (isset($config['api_key'])) {
            $this->apiKey = $config['api_key'];
        }
        if (isset($config['min_debounce_delay'])) {
            $this->minDebounceDelay = $config['min_debounce_delay'];
        }
    }

    /**
     * Build the URL and params for a forward geocoding search request.
     *
     * @param  array{countries?: string[], language?: string}  $options
     * @return array{url: string, params: array<string, mixed>}
     */
    abstract public function buildSearchRequest(string $query, array $options = []): array;

    /**
     * Build the URL and params for a reverse geocoding request.
     *
     * @param  array{language?: string}  $options
     * @return array{url: string, params: array<string, mixed>}
     */
    abstract public function buildReverseRequest(float $lat, float $lon, array $options = []): array;

    /**
     * Transform raw API response to normalized SearchResponse.
     */
    abstract public function transformResponse(array $rawResponse): SearchResponse;

    /**
     * Transform reverse geocoding response. Override if different from forward.
     */
    public function transformReverseResponse(array $rawResponse): SearchResponse
    {
        return $this->transformResponse($rawResponse);
    }

    /**
     * Set additional fields to exclude at runtime.
     *
     * @param  string[]  $fields
     */
    public function setExcludeFields(array $fields): static
    {
        $this->excludeFields = array_unique(array_merge($this->defaultExcludeFields, $fields));

        return $this;
    }

    /**
     * Get the combined exclusion list.
     *
     * @return string[]
     */
    public function getExcludeFields(): array
    {
        return $this->excludeFields ?: $this->defaultExcludeFields;
    }

    /**
     * Get only the provider's default exclusion fields.
     *
     * @return string[]
     */
    public function getDefaultExcludeFields(): array
    {
        return $this->defaultExcludeFields;
    }

    public function getMinDebounceDelay(): int
    {
        return $this->minDebounceDelay;
    }

    public function requiresApiKey(): bool
    {
        return true;
    }

    public function getApiKey(): ?string
    {
        return $this->apiKey;
    }

    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    public function getReverseBaseUrl(): string
    {
        return $this->reverseBaseUrl ?? $this->baseUrl;
    }

    /**
     * Normalize a single item from provider response to AddressResult.
     *
     * @param  array<string, mixed>  $item  Raw item from provider
     */
    protected function normalize(array $item): AddressResult
    {
        $excludeFields = $this->getExcludeFields();

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
            fn ($key) => ! isset($excludeSet[$key]),
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
