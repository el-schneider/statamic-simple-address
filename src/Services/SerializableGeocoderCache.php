<?php

namespace ElSchneider\StatamicSimpleAddress\Services;

use DateInterval;
use Geocoder\Collection;
use Geocoder\Model\Address;
use Geocoder\Model\AddressCollection;
use Psr\SimpleCache\CacheInterface;

class SerializableGeocoderCache implements CacheInterface
{
    private const CACHE_VERSION = 1;

    public function __construct(
        private CacheInterface $cache,
    ) {}

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->restore($this->cache->get($key)) ?? $default;
    }

    public function set(string $key, mixed $value, null|int|DateInterval $ttl = null): bool
    {
        return $this->cache->set($key, $this->normalize($value), $ttl);
    }

    public function delete(string $key): bool
    {
        return $this->cache->delete($key);
    }

    public function clear(): bool
    {
        return $this->cache->clear();
    }

    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        foreach ($keys as $key) {
            yield $key => $this->get($key, $default);
        }
    }

    public function setMultiple(iterable $values, null|int|DateInterval $ttl = null): bool
    {
        foreach ($values as $key => $value) {
            if (! $this->set($key, $value, $ttl)) {
                return false;
            }
        }

        return true;
    }

    public function deleteMultiple(iterable $keys): bool
    {
        foreach ($keys as $key) {
            if (! $this->delete($key)) {
                return false;
            }
        }

        return true;
    }

    public function has(string $key): bool
    {
        return $this->cache->has($key);
    }

    private function normalize(mixed $value): mixed
    {
        if (! $value instanceof Collection) {
            return $value;
        }

        return [
            'version' => self::CACHE_VERSION,
            'locations' => array_map(fn ($location) => $location->toArray(), $value->all()),
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
