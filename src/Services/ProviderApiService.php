<?php

namespace ElSchneider\StatamicSimpleAddress\Services;

use ElSchneider\StatamicSimpleAddress\Providers\AbstractProvider;
use ElSchneider\StatamicSimpleAddress\Providers\GeoapifyProvider;
use ElSchneider\StatamicSimpleAddress\Providers\GoogleProvider;
use ElSchneider\StatamicSimpleAddress\Providers\MapboxProvider;
use ElSchneider\StatamicSimpleAddress\Providers\NominatimProvider;
use Illuminate\Support\Facades\Cache;

class ProviderApiService
{
    /**
     * Built-in provider class mappings.
     *
     * @var array<string, class-string<AbstractProvider>>
     */
    protected static array $builtInProviders = [
        'nominatim' => NominatimProvider::class,
        'geoapify' => GeoapifyProvider::class,
        'google' => GoogleProvider::class,
        'mapbox' => MapboxProvider::class,
    ];

    /**
     * Resolve and instantiate a provider by name.
     */
    public function resolveProvider(string $name): AbstractProvider
    {
        $config = config("simple-address.providers.{$name}", []);

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

        // Check built-in providers
        if (isset(self::$builtInProviders[$name])) {
            $class = self::$builtInProviders[$name];

            return new $class($config);
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
        $configured = array_keys(config('simple-address.providers', []));
        $builtIn = array_keys(self::$builtInProviders);

        return array_unique(array_merge($configured, $builtIn));
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
