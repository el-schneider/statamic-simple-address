<?php

namespace Tests\Stubs;

use Geocoder\Collection;
use Geocoder\Model\Address;
use Geocoder\Model\AddressCollection;
use Geocoder\Model\AdminLevel;
use Geocoder\Model\AdminLevelCollection;
use Geocoder\Model\Coordinates;
use Geocoder\Model\Country;
use Geocoder\Provider\Provider;
use Geocoder\Query\GeocodeQuery;
use Geocoder\Query\ReverseQuery;

class GoogleLikeProvider implements Provider
{
    public static int $calls = 0;

    public function geocodeQuery(GeocodeQuery $query): Collection
    {
        self::$calls++;

        return new AddressCollection([
            new Address(
                providedBy: 'google_maps',
                adminLevels: new AdminLevelCollection([
                    new AdminLevel(1, 'Baden-Württemberg', 'BW'),
                    new AdminLevel(2, 'Tübingen', 'TÜ'),
                ]),
                coordinates: new Coordinates(48.5216, 9.0576),
                streetNumber: '1',
                streetName: 'Holzmarkt',
                postalCode: '72070',
                locality: 'Tübingen',
                country: new Country('Germany', 'DE'),
            ),
        ]);
    }

    public function reverseQuery(ReverseQuery $query): Collection
    {
        self::$calls++;

        return new AddressCollection([
            Address::createFromArray([
                'providedBy' => 'google_maps',
                'latitude' => $query->getCoordinates()->getLatitude(),
                'longitude' => $query->getCoordinates()->getLongitude(),
                'locality' => 'Tübingen',
                'country' => 'Germany',
                'countryCode' => 'DE',
            ]),
        ]);
    }

    public function getName(): string
    {
        return 'google_maps';
    }
}
