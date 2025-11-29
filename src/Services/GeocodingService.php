<?php

namespace ElSchneider\StatamicSimpleAddress\Services;

use ElSchneider\StatamicSimpleAddress\Data\SearchResponse;
use ElSchneider\StatamicSimpleAddress\Exceptions\ProviderApiException;
use ElSchneider\StatamicSimpleAddress\Providers\AbstractProvider;
use ElSchneider\StatamicSimpleAddress\Providers\ProviderRegistry;
use Illuminate\Support\Facades\Cache;

class GeocodingService
{
    /**
     * Search for an address and return the raw API response.
     *
     * @param  array{countries?: string[], language?: string}  $options
     * @return array{response: array, provider: AbstractProvider}
     */
    public function searchRaw(string $providerName, string $query, array $options = []): array
    {
        $provider = $this->resolveProvider($providerName);
        $request = $provider->buildSearchRequest($query, $options);
        $response = $this->fetch($request['url'], $request['params']);

        return ['response' => $response, 'provider' => $provider];
    }

    /**
     * Search for an address and return transformed results.
     *
     * @param  array{countries?: string[], language?: string}  $options
     */
    public function search(string $providerName, string $query, array $options = []): SearchResponse
    {
        $result = $this->searchRaw($providerName, $query, $options);

        return $result['provider']->transformResponse($result['response']);
    }

    /**
     * Reverse geocode coordinates and return the raw API response.
     *
     * @param  array{language?: string}  $options
     * @return array{response: array, provider: AbstractProvider}
     */
    public function reverseRaw(string $providerName, float $lat, float $lon, array $options = []): array
    {
        $provider = $this->resolveProvider($providerName);
        $request = $provider->buildReverseRequest($lat, $lon, $options);
        $response = $this->fetch($request['url'], $request['params']);

        return ['response' => $response, 'provider' => $provider];
    }

    /**
     * Reverse geocode coordinates and return transformed results.
     *
     * @param  array{language?: string}  $options
     */
    public function reverse(string $providerName, float $lat, float $lon, array $options = []): SearchResponse
    {
        $result = $this->reverseRaw($providerName, $lat, $lon, $options);

        return $result['provider']->transformReverseResponse($result['response']);
    }

    /**
     * Resolve and instantiate a provider by name.
     */
    public function resolveProvider(string $name): AbstractProvider
    {
        $config = $this->getConfig("simple-address.providers.{$name}", []);

        // Check for custom class in config
        if (isset($config['class'])) {
            $class = $config['class'];
            if (! class_exists($class)) {
                throw new \InvalidArgumentException("Provider class '{$class}' not found");
            }
            if (! is_subclass_of($class, AbstractProvider::class)) {
                throw new \InvalidArgumentException('Provider class must extend AbstractProvider');
            }

            return new $class($config);
        }

        // Use built-in provider from registry
        if (ProviderRegistry::has($name)) {
            return ProviderRegistry::make($name, $config);
        }

        throw new \InvalidArgumentException("Provider '{$name}' not found");
    }

    /**
     * Get list of available provider names.
     *
     * @return string[]
     */
    public function getAvailableProviders(): array
    {
        $configured = array_keys($this->getConfig('simple-address.providers', []));
        $builtIn = ProviderRegistry::names();

        return array_unique(array_merge($configured, $builtIn));
    }

    /**
     * Get config value (works with or without Laravel).
     */
    private function getConfig(string $key, mixed $default = null): mixed
    {
        if (function_exists('config')) {
            try {
                return config($key, $default);
            } catch (\Throwable) {
                // Fall through if Laravel isn't booted
            }
        }

        return $default;
    }

    /**
     * Validate API key is configured if required by provider.
     */
    public function validateApiKey(AbstractProvider $provider, string $name): void
    {
        if ($provider->requiresApiKey() && empty($provider->getApiKey())) {
            throw new \InvalidArgumentException(
                "Please configure the API key for {$name} in your environment variables."
            );
        }
    }

    /**
     * Generate cache key from data.
     */
    public function generateCacheKey(string $prefix, array $data): string
    {
        return $prefix.':'.hash('sha256', json_encode($data));
    }

    /**
     * Get cached response or execute fetcher.
     *
     * @return array{data: mixed, cached: bool}
     */
    public function getCachedOrFetch(string $cacheKey, callable $fetcher): array
    {
        if (Cache::has($cacheKey)) {
            return ['data' => Cache::get($cacheKey), 'cached' => true];
        }

        $data = $fetcher();

        // Cache deferred to avoid blocking
        \Illuminate\Support\defer(fn () => Cache::put($cacheKey, $data, now()->addYear()));

        return ['data' => $data, 'cached' => false];
    }

    /**
     * Enforce minimum debounce delay for a provider to prevent exceeding rate limits.
     * Uses atomic locking to check if enough time has passed since the last request.
     *
     * @return bool True if this request should proceed, false if too soon
     */
    public function enforceMinimumDelay(string $provider, int $minDelayMs): bool
    {
        if ($minDelayMs <= 0) {
            return true;
        }

        $lockKey = "simple_address:throttle:{$provider}";
        $timeKey = "{$lockKey}:time";

        $lock = Cache::lock($lockKey, 5);
        if (! $lock->get()) {
            return false;
        }

        try {
            $lastRequestTime = Cache::get($timeKey);

            if ($lastRequestTime) {
                $elapsed = now()->diffInMilliseconds($lastRequestTime);
                if ($elapsed < $minDelayMs) {
                    return false;
                }
            }

            Cache::put($timeKey, now(), 60);

            return true;
        } finally {
            $lock->release();
        }
    }

    /**
     * Build the full URL with query parameters.
     */
    public function buildUrl(string $url, array $params): string
    {
        if (empty($params)) {
            return $url;
        }

        return $url.'?'.http_build_query($params);
    }

    /**
     * Fetch data from a URL (works with or without Laravel).
     *
     * @throws ProviderApiException
     */
    public function fetch(string $url, array $params = []): array
    {
        $fullUrl = $this->buildUrl($url, $params);

        // Try Laravel's HTTP client if available
        if (class_exists(\Illuminate\Support\Facades\Http::class) && function_exists('app')) {
            try {
                return $this->fetchWithLaravel($url, $params);
            } catch (\Throwable) {
                // Fall through to native PHP if Laravel isn't booted
            }
        }

        return $this->fetchNative($fullUrl);
    }

    /**
     * Fetch using Laravel's HTTP client.
     *
     * @throws ProviderApiException
     */
    private function fetchWithLaravel(string $url, array $params): array
    {
        $response = \Illuminate\Support\Facades\Http::withHeaders([
            'User-Agent' => config('app.name', 'Statamic Simple Address'),
        ])->get($url, $params);

        if (! $response->successful()) {
            throw new ProviderApiException('Provider API request failed', $response->status());
        }

        return $response->json();
    }

    /**
     * Fetch using native PHP (for CLI scripts without Laravel).
     *
     * @throws ProviderApiException
     */
    private function fetchNative(string $url): array
    {
        $context = stream_context_create([
            'http' => [
                'header' => 'User-Agent: StatamicSimpleAddress',
                'timeout' => 30,
            ],
        ]);

        $response = @file_get_contents($url, false, $context);

        if ($response === false) {
            throw new ProviderApiException('Failed to fetch from API', 0);
        }

        $data = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new ProviderApiException('Invalid JSON response from API', 0);
        }

        return $data;
    }
}
