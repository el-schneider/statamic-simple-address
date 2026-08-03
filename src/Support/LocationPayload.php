<?php

namespace ElSchneider\StatamicSimpleAddress\Support;

use Geocoder\Location;
use Geocoder\Provider\Nominatim\Model\NominatimAddress;
use Illuminate\Support\Arr;

/**
 * Turns a geocoder result into the plain array the field shows, caches and saves.
 *
 * Not a JsonResource on purpose: its filter() reindexes nested arrays whose keys are
 * all numeric, which would flatten adminLevels from a map keyed by level into a list.
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
     * The shared model has no field for a place's own name, so peaks, huts and hotels
     * come out nameless. Each provider labels its own results better than we can.
     *
     * Nominatim is a hard dependency and names the field differently; the rest are
     * optional packages, so they are duck-typed.
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
