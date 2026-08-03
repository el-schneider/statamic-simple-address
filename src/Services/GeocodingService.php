<?php

namespace ElSchneider\StatamicSimpleAddress\Services;

use Closure;
use ElSchneider\StatamicSimpleAddress\Support\LocationPayload;
use Geocoder\Query\GeocodeQuery;
use Geocoder\Query\ReverseQuery;
use Geocoder\StatefulGeocoder;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class GeocodingService
{
    private const DEFAULT_LOCALE = 'en';

    /**
     * Bump whenever the payload shape changes. Cached entries outlive addon upgrades:
     * the default duration is roughly four months.
     */
    private const CACHE_VERSION = 'v1';

    private StatefulGeocoder $geocoder;

    public function __construct()
    {
        $this->geocoder = new StatefulGeocoder($this->buildProvider(), self::DEFAULT_LOCALE);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function geocode(GeocodeQuery $query): array
    {
        $query = $this->withResolvedLocale($query);

        return $this->remember($query, fn () => $this->geocoder->geocodeQuery($query)->all());
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function reverse(ReverseQuery $query): array
    {
        $query = $this->withResolvedLocale($query);

        return $this->remember($query, fn () => $this->geocoder->reverseQuery($query)->all());
    }

    /**
     * Cache the mapped payload rather than the provider's result objects. Nothing has to
     * be rebuilt on a cache hit, so nothing can be lost on the way, and the cache only
     * ever holds plain data — which is all Laravel will unserialize back.
     */
    private function remember(GeocodeQuery|ReverseQuery $query, Closure $lookup): array
    {
        $payload = fn () => array_map(LocationPayload::fromLocation(...), $lookup());

        if (! config('simple-address.cache.enabled')) {
            return $payload();
        }

        return Cache::store(config('simple-address.cache.store'))->remember(
            $this->cacheKey($query),
            config('simple-address.cache.duration'),
            $payload
        );
    }

    private function cacheKey(GeocodeQuery|ReverseQuery $query): string
    {
        return sprintf(
            'simple-address.%s.%s.%s',
            self::CACHE_VERSION,
            config('simple-address.provider'),
            sha1((string) $query)
        );
    }

    /**
     * StatefulGeocoder fills in the default locale itself, but only after the query has
     * been handed to it — too late for a cache key that has to describe the real request.
     * Empty counts as unset there, so it has to count as unset here too.
     */
    private function withResolvedLocale(GeocodeQuery|ReverseQuery $query): GeocodeQuery|ReverseQuery
    {
        return $query->withLocale($query->getLocale() ?: self::DEFAULT_LOCALE);
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

            // Log outgoing requests
            $stack->push(Middleware::mapRequest(function ($request) {
                Log::debug('Guzzle request', [
                    'method' => $request->getMethod(),
                    'url' => (string) $request->getUri(),
                    'headers' => $request->getHeaders(),
                    'body' => $request->getBody()->getContents(),
                ]);
                // Reset stream position after logging
                $request->getBody()->rewind();

                return $request;
            }));

            // Log incoming responses
            $stack->push(Middleware::mapResponse(function ($response) {
                Log::debug('Guzzle response', [
                    'status_code' => $response->getStatusCode(),
                    'reason_phrase' => $response->getReasonPhrase(),
                    'headers' => $response->getHeaders(),
                    'body' => (string) $response->getBody(),
                ]);

                return $response;
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
