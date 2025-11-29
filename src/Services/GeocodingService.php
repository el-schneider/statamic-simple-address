<?php

namespace ElSchneider\StatamicSimpleAddress\Services;

use ElSchneider\StatamicSimpleAddress\Data\AddressResult;
use Geocoder\Provider\Cache\ProviderCache;
use Geocoder\Query\GeocodeQuery;
use Geocoder\Query\ReverseQuery;
use Geocoder\StatefulGeocoder;
use GuzzleHttp\Client;

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

    public function geocode(GeocodeQuery $query): array
    {
        $results = $this->geocoder->geocodeQuery($query);

        return array_map(
            fn ($address) => new AddressResult(
                label: $this->formatAddressLabel($address),
                lat: (string) $address->getCoordinates()->getLatitude(),
                lon: (string) $address->getCoordinates()->getLongitude(),
                data: array_filter($address->toArray(), fn ($v) => $v !== null && $v !== ''),
            ),
            $results->all()
        );
    }

    public function reverse(ReverseQuery $query): array
    {
        $results = $this->geocoder->reverseQuery($query);

        return array_map(
            fn ($address) => new AddressResult(
                label: $this->formatAddressLabel($address),
                lat: (string) $address->getCoordinates()->getLatitude(),
                lon: (string) $address->getCoordinates()->getLongitude(),
                data: array_filter($address->toArray(), fn ($v) => $v !== null && $v !== ''),
            ),
            $results->all()
        );
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
        $httpClient = new Client;
        $args = $this->getConstructorArgs($providerConfig);

        // If provider has a factory method
        if (isset($providerConfig['factory'])) {
            $factory = $providerConfig['factory'];

            return $class::$factory($httpClient, ...$args);
        }

        // Standard instantiation
        return new $class($httpClient, ...$args);
    }

    private function getConstructorArgs(array $config): array
    {
        return $config['args'] ?? [];
    }

    private function formatAddressLabel($location): string
    {
        $parts = [];

        if ($location->getStreetNumber()) {
            $parts[] = $location->getStreetNumber();
        }

        if ($location->getStreetName()) {
            $parts[] = $location->getStreetName();
        }

        if ($location->getLocality()) {
            $parts[] = $location->getLocality();
        }

        if ($location->getAdminLevels()->first()?->getName()) {
            $parts[] = $location->getAdminLevels()->first()->getName();
        }

        if ($location->getCountry()?->getName()) {
            $parts[] = $location->getCountry()->getName();
        }

        return implode(', ', $parts);
    }
}
