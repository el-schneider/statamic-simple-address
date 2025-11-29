<?php

namespace ElSchneider\StatamicSimpleAddress\Services;

use ElSchneider\StatamicSimpleAddress\Data\AddressResult;
use Geocoder\Provider\Nominatim\Nominatim;
use Geocoder\Query\GeocodeQuery;
use Geocoder\Query\ReverseQuery;
use Geocoder\StatefulGeocoder;
use GuzzleHttp\Client;

class GeocodingService
{
    private StatefulGeocoder $geocoder;

    public function __construct()
    {
        $httpClient = new Client;
        $userAgent = config('app.name', 'Statamic Simple Address');
        $provider = Nominatim::withOpenStreetMapServer($httpClient, $userAgent);

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
