<?php

namespace Tests\Stubs;

use Geocoder\Collection;
use Geocoder\Model\AddressCollection;
use Geocoder\Provider\Nominatim\Model\NominatimAddress;
use Geocoder\Provider\Provider;
use Geocoder\Query\GeocodeQuery;
use Geocoder\Query\ReverseQuery;

/**
 * Returns the model the real Nominatim provider would build, without the HTTP round
 * trip. What the provider makes of a raw response is its own business, covered by its
 * own test suite; ours only cares what we make of the model.
 *
 * The one assumption that spans the two — that a display name is populated at all — is
 * pinned against a recorded response in LocationPayloadTest.
 */
class NominatimProvider implements Provider
{
    public static int $calls = 0;

    public static ?NominatimAddress $result = null;

    public static function address(array $data, string $displayName): NominatimAddress
    {
        return NominatimAddress::createFromArray(['providedBy' => 'nominatim'] + $data)
            ->withDisplayName($displayName);
    }

    public function geocodeQuery(GeocodeQuery $query): Collection
    {
        self::$calls++;

        return new AddressCollection([self::$result]);
    }

    public function reverseQuery(ReverseQuery $query): Collection
    {
        self::$calls++;

        return new AddressCollection([self::$result]);
    }

    public function getName(): string
    {
        return 'nominatim';
    }
}
