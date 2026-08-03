<?php

namespace ElSchneider\StatamicSimpleAddress\Support;

use Geocoder\Location;
use Geocoder\Provider\Nominatim\Model\NominatimAddress;
use Illuminate\Support\Arr;

/**
 * Maps a geocoder result to the plain array the addon serves, caches and stores
 * in entry content. Provider models are only ever read here, never handed on:
 * they cannot survive a cache round trip, this array can.
 */
class LocationPayload
{
    public static function fromLocation(Location $location): array
    {
        $data = Arr::except($location->toArray(), ['label', 'latitude', 'longitude']);

        return array_filter([
            'label' => self::label($location),
            'lat' => (string) $location->getCoordinates()->getLatitude(),
            'lon' => (string) $location->getCoordinates()->getLongitude(),
            ...$data,
        ], fn ($value) => $value !== null && $value !== '');
    }

    private static function label(Location $location): string
    {
        $adminLevels = $location->getAdminLevels();

        $parts = array_filter([
            $location->getStreetNumber(),
            $location->getStreetName(),
            $location->getLocality(),
            count($adminLevels) > 0 ? $adminLevels->first()->getName() : null,
            $location->getCountry()?->getName(),
        ]);

        // A place's own name goes in front, because a POI can sit on a named street (an
        // alpine hut on a trail) just as well as nowhere at all — a street name proves
        // nothing. Unless a part already says it: a town is its own locality, and an
        // address leads its display name with the house number.
        $name = self::name($location);

        if ($name !== null && ! in_array($name, $parts, true)) {
            array_unshift($parts, $name);
        }

        return implode(', ', $parts);
    }

    /**
     * The place's own name — a peak, a hut, a POI. The base geocoder model has no field
     * for it, so it has to come from whatever the provider model exposes.
     */
    private static function name(Location $location): ?string
    {
        if (! $location instanceof NominatimAddress) {
            return null;
        }

        // geocoder-php never maps Nominatim's top-level `name`, but display_name leads
        // with it. It is also the only field present on reverse lookups, where the
        // provider omits category and type.
        // ponytail: a name containing a comma gets cut short; the real fix is upstream.
        return trim(explode(',', (string) $location->getDisplayName())[0]) ?: null;
    }
}
