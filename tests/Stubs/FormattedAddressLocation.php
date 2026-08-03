<?php

namespace Tests\Stubs;

use Geocoder\Model\Address;

/**
 * Stands in for Google, Mapbox and anything else exposing getFormattedAddress().
 * Those provider packages are optional, so the addon duck-types the method rather
 * than depending on their classes — this is what that duck-typing has to catch.
 */
class FormattedAddressLocation extends Address
{
    public string $formattedAddress = '';

    public function getFormattedAddress(): string
    {
        return $this->formattedAddress;
    }
}
