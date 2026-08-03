<?php

use ElSchneider\StatamicSimpleAddress\Support\LocationPayload;
use Geocoder\Model\Address;
use Geocoder\Provider\Nominatim\Model\NominatimAddress;
use Tests\Stubs\FormattedAddressLocation;

$base = ['providedBy' => 'test', 'latitude' => 45.97, 'longitude' => 7.65];

test('that the label is the one the provider itself produced', function () use ($base) {
    // A peak: no street, no locality — only the provider's label names it.
    $location = NominatimAddress::createFromArray($base + ['country' => 'Switzerland'])
        ->withDisplayName('Matterhorn, Zermatt, Valais, Switzerland');

    expect(LocationPayload::fromLocation($location)['label'])
        ->toBe('Matterhorn, Zermatt, Valais, Switzerland');
});

test('that providers exposing a formatted address are used without depending on them', function () use ($base) {
    $location = FormattedAddressLocation::createFromArray($base + ['country' => 'Montenegro']);
    $location->formattedAddress = 'Durmitor, Žabljak, Montenegro';

    expect(LocationPayload::fromLocation($location)['label'])->toBe('Durmitor, Žabljak, Montenegro');
});

test('that a provider with no label of its own falls back to the address parts', function () use ($base) {
    $location = Address::createFromArray($base + [
        'streetNumber' => '123', 'streetName' => 'Main Street', 'locality' => 'London',
        'adminLevels' => [['level' => 1, 'name' => 'England', 'code' => 'GB-ENG']],
        'country' => 'United Kingdom',
    ]);

    expect(LocationPayload::fromLocation($location)['label'])
        ->toBe('123, Main Street, London, England, United Kingdom');
});

test('that a blank provider label falls back rather than blanking the result', function () use ($base) {
    $location = NominatimAddress::createFromArray($base + ['locality' => 'London', 'country' => 'United Kingdom'])
        ->withDisplayName('   ');

    expect(LocationPayload::fromLocation($location)['label'])->toBe('London, United Kingdom');
});

test('that the payload is plain data, keyed admin levels included', function () use ($base) {
    $payload = LocationPayload::fromLocation(Address::createFromArray($base + [
        'locality' => 'London',
        'adminLevels' => [['level' => 1, 'name' => 'England', 'code' => 'GB-ENG']],
    ]));

    expect($payload['adminLevels'])->toHaveKey(1)
        ->and($payload)->not->toHaveKey('streetName')
        ->and(json_decode(json_encode($payload), true))->toBe($payload);
});
