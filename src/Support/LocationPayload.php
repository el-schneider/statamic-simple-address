<?php

namespace ElSchneider\StatamicSimpleAddress\Support;

use Geocoder\Location;
use Geocoder\Provider\Nominatim\Model\NominatimAddress;
use Illuminate\Support\Arr;

/**
 * Turns a geocoder result into the plain array the field shows, caches and saves.
 *
 * Deliberately not a JsonResource: that recurses through nested arrays and reindexes
 * any with all-numeric keys, which would turn adminLevels from a map keyed by level
 * into a list. Escaping that used to need a stdClass cast, and an object is precisely
 * what a cache running under `serializable_classes = false` cannot give back.
 */
class LocationPayload
{
    public static function fromLocation(Location $location): array
    {
        $data = Arr::except($location->toArray(), ['label', 'latitude', 'longitude']);

        return array_filter([
            'label' => self::providerLabel($location) ?? self::structuredLabel($location),
            'lat' => (string) $location->getCoordinates()->getLatitude(),
            'lon' => (string) $location->getCoordinates()->getLongitude(),
            ...$data,
        ], fn ($value) => $value !== null && $value !== '' && $value !== []);
    }

    /**
     * Providers ship their own human-readable label, and they describe their own results
     * better than we can rebuild them from the shared model — which has no field for a
     * place's own name, so peaks, huts and hotels come out nameless or blank.
     *
     * Nominatim names the field differently. Everything else is duck-typed, because
     * those provider packages are optional.
     */
    private static function providerLabel(Location $location): ?string
    {
        $label = match (true) {
            $location instanceof NominatimAddress => $location->getDisplayName(),
            method_exists($location, 'getFormattedAddress') => $location->getFormattedAddress(),
            default => null,
        };

        $label = is_string($label) ? trim($label) : '';

        return $label === '' ? null : $label;
    }

    private static function structuredLabel(Location $location): string
    {
        $adminLevels = $location->getAdminLevels();

        return implode(', ', array_filter([
            $location->getStreetNumber(),
            $location->getStreetName(),
            $location->getLocality(),
            count($adminLevels) > 0 ? $adminLevels->first()->getName() : null,
            $location->getCountry()?->getName(),
        ]));
    }
}
