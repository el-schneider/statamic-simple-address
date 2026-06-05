<?php

namespace ElSchneider\StatamicSimpleAddress\Services;

use Geocoder\Collection;
use Geocoder\Model\Address;
use Geocoder\Model\AddressCollection;
use Geocoder\Provider\Provider;
use Geocoder\Query\GeocodeQuery;
use Geocoder\Query\ReverseQuery;
use Psr\SimpleCache\CacheInterface;

class SerializableGeocoderCache implements Provider
{
    private const CACHE_VERSION = 1;

    public function __construct(
        private Provider $provider,
        private CacheInterface $cache,
        private ?int $lifetime = null,
        private bool $separateCache = false,
    ) {}

    public function geocodeQuery(GeocodeQuery $query): Collection
    {
        return $this->remember($this->cacheKey($query), fn () => $this->provider->geocodeQuery($query));
    }

    public function reverseQuery(ReverseQuery $query): Collection
    {
        return $this->remember($this->cacheKey($query), fn () => $this->provider->reverseQuery($query));
    }

    public function getName(): string
    {
        return sprintf('%s (cache)', $this->provider->getName());
    }

    public function __call(string $method, array $args): mixed
    {
        return call_user_func_array([$this->provider, $method], $args);
    }

    private function remember(string $key, callable $callback): Collection
    {
        $cached = $this->cache->get($key);

        if ($cached = $this->restore($cached)) {
            return $cached;
        }

        $result = $callback();

        $this->cache->set($key, $this->normalize($result), $this->lifetime);

        return $result;
    }

    private function cacheKey(GeocodeQuery|ReverseQuery $query): string
    {
        return 'ssa-geocoder-v'.self::CACHE_VERSION.sha1((string) $query.($this->separateCache ? $this->provider->getName() : ''));
    }

    private function normalize(Collection $collection): array
    {
        return [
            'version' => self::CACHE_VERSION,
            'locations' => array_map(fn ($location) => $location->toArray(), $collection->all()),
        ];
    }

    private function restore(mixed $cached): ?Collection
    {
        if (! is_array($cached) || ($cached['version'] ?? null) !== self::CACHE_VERSION || ! is_array($cached['locations'] ?? null)) {
            return null;
        }

        $locations = [];

        foreach ($cached['locations'] as $location) {
            if (! is_array($location)) {
                return null;
            }

            $locations[] = Address::createFromArray($location);
        }

        return new AddressCollection($locations);
    }
}
