<?php

namespace Tests\Stubs;

use Geocoder\Collection;
use Geocoder\Model\Address;
use Geocoder\Model\AddressCollection;
use Geocoder\Model\Coordinates;
use Geocoder\Model\Country;
use Geocoder\Provider\Provider;
use Geocoder\Query\GeocodeQuery;
use Geocoder\Query\ReverseQuery;

/**
 * Stub provider that returns fixture data instead of making HTTP requests.
 * Used in tests to avoid external API dependencies.
 */
class StubProvider implements Provider
{
    public function geocodeQuery(GeocodeQuery $query): Collection
    {
        // Return a single fixture address matching the common test query
        $address = new Address(
            providedBy: 'stub',
            adminLevels: $this->createAdminLevels(),
            coordinates: new Coordinates(51.5074, -0.1278),
            streetNumber: '123',
            streetName: 'Main Street',
            locality: 'London',
            country: new Country('United Kingdom', 'GB'),
            postalCode: 'SW1A 1AA',
        );

        return new AddressCollection([$address]);
    }

    public function reverseQuery(ReverseQuery $query): Collection
    {
        // Return the same fixture for reverse queries
        $address = new Address(
            providedBy: 'stub',
            adminLevels: $this->createAdminLevels(),
            coordinates: new Coordinates(
                $query->getCoordinates()->getLatitude(),
                $query->getCoordinates()->getLongitude()
            ),
            streetNumber: '123',
            streetName: 'Main Street',
            locality: 'London',
            country: new Country('United Kingdom', 'GB'),
            postalCode: 'SW1A 1AA',
        );

        return new AddressCollection([$address]);
    }

    public function getName(): string
    {
        return 'stub';
    }

    private function createAdminLevels(): \Geocoder\Model\AdminLevelCollection
    {
        return new \Geocoder\Model\AdminLevelCollection([
            new \Geocoder\Model\AdminLevel(1, 'England', 'GB-ENG'),
        ]);
    }
}
