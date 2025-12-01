<?php

namespace ElSchneider\StatamicSimpleAddress\Services;

use Geocoder\Provider\Cache\ProviderCache;
use Geocoder\Query\GeocodeQuery;
use Geocoder\Query\ReverseQuery;
use Geocoder\StatefulGeocoder;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use Illuminate\Support\Facades\Log;

class GeocodingService
{
    private StatefulGeocoder $geocoder;

    public function __construct()
    {
        $provider = $this->buildProvider();

        // Wrap with caching if enabled
        if (config('simple-address.cache.enabled')) {
            $provider = new ProviderCache(
                $provider,
                app('cache')->store(config('simple-address.cache.store')),
                config('simple-address.cache.duration')
            );
        }

        $this->geocoder = new StatefulGeocoder($provider, 'en');
    }

    public function geocode(GeocodeQuery $query)
    {
        return $this->geocoder->geocodeQuery($query)->all();
    }

    public function reverse(ReverseQuery $query)
    {
        return $this->geocoder->reverseQuery($query)->all();
    }

    private function buildProvider()
    {
        $providerName = config('simple-address.provider');
        $providerConfig = config("simple-address.providers.{$providerName}");

        if (! $providerConfig) {
            throw new \InvalidArgumentException(
                "Provider '{$providerName}' is not configured in simple-address config."
            );
        }

        $class = $providerConfig['class'];
        $httpClient = $this->createHttpClient();
        $args = $this->getConstructorArgs($providerConfig);

        // If provider has a factory method
        if (isset($providerConfig['factory'])) {
            $factory = $providerConfig['factory'];

            return $class::$factory($httpClient, ...$args);
        }

        // Standard instantiation
        return new $class($httpClient, ...$args);
    }

    private function createHttpClient(): Client
    {
        $config = [];

        if (config('app.debug')) {
            $stack = HandlerStack::create();
            $stack->push(Middleware::tap(function ($request) {
                Log::debug('Guzzle request', [
                    'method' => $request->getMethod(),
                    'url' => (string) $request->getUri(),
                    'headers' => $request->getHeaders(),
                    'body' => $request->getBody()->getContents(),
                ]);
                // Reset stream position after logging
                $request->getBody()->rewind();
            }));

            $config['handler'] = $stack;
        }

        return new Client($config);
    }

    private function getConstructorArgs(array $config): array
    {
        return $config['args'] ?? [];
    }
}
