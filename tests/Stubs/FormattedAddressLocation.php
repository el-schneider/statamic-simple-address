<?php

namespace Tests\Stubs;

use Geocoder\Model\Address;

/**
 * Stands in for Google, Mapbox and anything else exposing getFormattedAddress(),
 * which the addon duck-types because those provider packages are optional.
 */
class FormattedAddressLocation extends Address
{
    public string $formattedAddress = '';

    public function getFormattedAddress(): string
    {
        return $this->formattedAddress;
    }
}
