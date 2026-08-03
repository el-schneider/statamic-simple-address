<?php

namespace ElSchneider\StatamicSimpleAddress\Support;

use Geocoder\Location;
use Geocoder\Provider\Nominatim\Model\NominatimAddress;
use Illuminate\Support\Arr;

/**
 * Turns a geocoder result into the plain array the field shows, caches and saves.
 *
 * A JsonResource would break adminLevels: filter() renumbers nested arrays whose keys
 * are all numeric, and adminLevels is keyed by level.
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
     * Location has no field for a place's own name, so a peak or a hut ends up labelled
     * after its surroundings. Providers ship a label of their own instead.
     *
     * Nominatim is a required dependency and names it differently. Other providers are
     * optional packages, so the method is probed rather than the class.
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
