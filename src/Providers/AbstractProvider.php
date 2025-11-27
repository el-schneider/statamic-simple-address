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
     * @param  array{countries?: string[], language?: string}  $options
     * @return array{url: string, params: array<string, mixed>}
     */
    abstract public function buildSearchRequest(string $query, array $options = []): array;

    /**
     * @param  array{language?: string}  $options
     * @return array{url: string, params: array<string, mixed>}
     */
    abstract public function buildReverseRequest(float $lat, float $lon, array $options = []): array;

    abstract public function transformResponse(array $rawResponse): SearchResponse;

    public function transformReverseResponse(array $rawResponse): SearchResponse
    {
        return $this->transformResponse($rawResponse);
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
     * Create an AddressResult from mapped data.
     */
    protected function createResult(string $label, string $lat, string $lon, array $data = []): AddressResult
    {
        return new AddressResult($label, $lat, $lon, $data);
    }
}
