<?php

namespace ElSchneider\StatamicSimpleAddress\Services;

use Illuminate\Support\Facades\Cache;

class ProviderApiService
{
    /**
     * Load provider configuration with validation
     */
    public function loadProviderConfig(string $provider): array
    {
        $config = config("simple-address.providers.{$provider}");

        if (! $config) {
            throw new \InvalidArgumentException("Provider '{$provider}' not found in configuration");
        }

        return $config;
    }

    /**
     * Validate API key is configured if required by provider
     */
    public function validateApiKey(array $config, string $provider): void
    {
        $apiKeyRequired = ! empty($config['api_key_param_name']);
        $apiKeyProvided = ! empty($config['api_key']);

        if ($apiKeyRequired && ! $apiKeyProvided) {
            throw new \InvalidArgumentException(
                "Please configure the API key for {$provider} in your environment variables or settings."
            );
        }
    }

    /**
     * Validate transformer class exists
     */
    public function validateTransformer(array $config): void
    {
        $transformerClass = $config['transformer'] ?? null;

        if (! $transformerClass || ! class_exists($transformerClass)) {
            throw new \InvalidArgumentException('Transformer for provider not found');
        }
    }

    /**
     * Build exclusion field list from defaults and additional fields
     */
    public function buildExclusionList(array $config, array $additionalFields = []): array
    {
        $defaultExclusions = $config['default_exclude_fields'] ?? [];

        return array_unique(array_merge($defaultExclusions, $additionalFields));
    }

    /**
     * Generate cache key from data
     */
    public function generateCacheKey(string $prefix, array $data): string
    {
        return $prefix.':'.hash('sha256', json_encode($data));
    }

    /**
     * Get cached response or execute fetcher
     */
    public function getCachedOrFetch(string $cacheKey, callable $fetcher): mixed
    {
        if (Cache::has($cacheKey)) {
            return ['data' => Cache::get($cacheKey), 'cached' => true];
        }

        $data = $fetcher();

        // Cache deferred to avoid blocking
        \Illuminate\Support\defer(fn () => Cache::put($cacheKey, $data, now()->addYear()));

        return ['data' => $data, 'cached' => false];
    }
}
