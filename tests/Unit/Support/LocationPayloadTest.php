<?php

use ElSchneider\StatamicSimpleAddress\Support\LocationPayload;
use Tests\Stubs\GoogleLikeProvider;
use Tests\Stubs\NominatimFixtureProvider;

test('that the label keeps the place own name', function (string $fixture, string $label) {
    $location = NominatimFixtureProvider::locations($fixture)[0];

    expect(LocationPayload::fromLocation($location)['label'])->toBe($label);
})->with([
    // A peak has no street and no name of its own in the structured fields.
    ['search-peak', 'Matterhorn, Zermatt, Wallis, Schweiz'],
    // An alpine hut sits on a named trail, so a street name is no proof of an address.
    ['search-alpine-hut', 'Berliner Hütte, Zemmgrund, Mayrhofen, Tirol, Österreich'],
    // A town is its own name; it must not end up in the label twice.
    ['search-town', 'Tübingen, Baden-Württemberg, Deutschland'],
    // A plain address leads its display name with the house number, same story.
    ['search-building', '1, Holzmarkt, Tübingen, Baden-Württemberg, Deutschland'],
    // Reverse lookups carry no category or type, only the display name.
    ['reverse-poi', 'Heiliger Bernhard, Obre Stafel, Zermatt, Wallis, Schweiz'],
    // A city sharing its state's name repeats it legitimately; only the prepended name
    // is deduped, never the hierarchy itself.
    ['search-repeated-names', 'New York, New York, United States'],
]);

test('that providers without a name field fall back to the structured parts', function () {
    $location = (new GoogleLikeProvider)->geocodeQuery(Geocoder\Query\GeocodeQuery::create('x'))->first();

    expect(LocationPayload::fromLocation($location)['label'])
        ->toBe('1, Holzmarkt, Tübingen, Baden-Württemberg, Germany');
});

test('that the payload is plain data and drops empty values', function () {
    $payload = LocationPayload::fromLocation(NominatimFixtureProvider::locations('search-peak')[0]);

    expect($payload)->toHaveKeys(['label', 'lat', 'lon', 'providedBy', 'locality', 'country'])
        ->and($payload['lat'])->toBe('45.9764263')
        ->and($payload)->not->toHaveKey('streetName')
        ->and(json_decode(json_encode($payload), true))->toBe($payload);
});
