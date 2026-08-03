<?php

use ElSchneider\StatamicSimpleAddress\Support\LocationPayload;
use Geocoder\Provider\Nominatim\Model\NominatimAddress;
use Geocoder\Provider\Nominatim\Nominatim;
use Geocoder\Query\GeocodeQuery;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Tests\Stubs\GoogleLikeProvider;
use Tests\Stubs\NominatimProvider;

$swiss = ['latitude' => 45.97, 'longitude' => 7.65, 'country' => 'Schweiz'];
$german = ['latitude' => 48.52, 'longitude' => 9.05, 'country' => 'Deutschland'];

test('that the label keeps the place own name', function (array $address, string $displayName, string $label) {
    $location = NominatimProvider::address($address, $displayName);

    expect(LocationPayload::fromLocation($location)['label'])->toBe($label);
})->with([
    'a peak has no address of its own' => [
        [...$swiss, 'locality' => 'Zermatt', 'adminLevels' => [['level' => 1, 'name' => 'Wallis']]],
        'Matterhorn, Zermatt, Visp, Wallis, 3920, Schweiz',
        'Matterhorn, Zermatt, Wallis, Schweiz',
    ],
    // The case the reported fix misses: a hut on a named trail has a street name, so
    // the absence of one cannot be the condition for adding the name.
    'an alpine hut sits on a named trail' => [
        ['latitude' => 47.05, 'longitude' => 11.83, 'country' => 'Österreich',
            'streetName' => 'Zemmgrund', 'locality' => 'Mayrhofen',
            'adminLevels' => [['level' => 1, 'name' => 'Tirol']]],
        'Berliner Hütte, Zemmgrund, Mayrhofen, Bezirk Schwaz, Tirol, 6295, Österreich',
        'Berliner Hütte, Zemmgrund, Mayrhofen, Tirol, Österreich',
    ],
    'a town is its own locality' => [
        [...$german, 'locality' => 'Tübingen', 'adminLevels' => [['level' => 1, 'name' => 'Baden-Württemberg']]],
        'Tübingen, Landkreis Tübingen, Baden-Württemberg, Deutschland',
        'Tübingen, Baden-Württemberg, Deutschland',
    ],
    'an address leads its display name with the house number' => [
        [...$german, 'streetNumber' => '1', 'streetName' => 'Holzmarkt', 'locality' => 'Tübingen',
            'adminLevels' => [['level' => 1, 'name' => 'Baden-Württemberg']]],
        '1, Holzmarkt, Altstadt, Tübingen, Baden-Württemberg, 72070, Deutschland',
        '1, Holzmarkt, Tübingen, Baden-Württemberg, Deutschland',
    ],
    // Only the name is deduped. A city sharing its state's name repeats it on purpose.
    'a city may share its state name' => [
        ['latitude' => 40.71, 'longitude' => -74.01, 'country' => 'United States',
            'locality' => 'New York', 'adminLevels' => [['level' => 1, 'name' => 'New York']]],
        'New York, United States',
        'New York, New York, United States',
    ],
]);

test('that providers without a name field fall back to the structured parts', function () {
    $location = (new GoogleLikeProvider)->geocodeQuery(GeocodeQuery::create('x'))->first();

    expect(LocationPayload::fromLocation($location)['label'])
        ->toBe('1, Holzmarkt, Tübingen, Baden-Württemberg, Germany');
});

test('that the payload is plain data and drops empty values', function () {
    $payload = LocationPayload::fromLocation(NominatimProvider::address(
        ['latitude' => 45.97, 'longitude' => 7.65, 'country' => 'Schweiz', 'locality' => 'Zermatt'],
        'Matterhorn, Zermatt, Schweiz',
    ));

    expect($payload)->toHaveKeys(['label', 'lat', 'lon', 'providedBy', 'locality', 'country'])
        ->and($payload['lat'])->toBe('45.97')
        ->and($payload)->not->toHaveKey('streetName')
        ->and(json_decode(json_encode($payload), true))->toBe($payload);
});

/**
 * The models above are hand-built, so they would keep passing if geocoder-php ever
 * stopped populating the one field the name is read from, while real lookups quietly
 * went back to nameless labels. This is the only test that needs a recorded response.
 *
 * Re-record it only if Nominatim's response format moves:
 *
 *   curl -A "statamic-simple-address tests" -o tests/__fixtures__/nominatim/search-alpine-hut.json \
 *     --get "https://nominatim.openstreetmap.org/search" \
 *     -d format=jsonv2 -d addressdetails=1 -d limit=1 \
 *     --data-urlencode "q=Berliner Hütte" --data-urlencode "accept-language=de"
 */
test('that the provider still populates the field the name comes from', function () {
    $body = file_get_contents(__DIR__.'/../../__fixtures__/nominatim/search-alpine-hut.json');
    $handler = HandlerStack::create(new MockHandler([new Response(200, [], $body)]));
    $provider = Nominatim::withOpenStreetMapServer(new Client(['handler' => $handler]), 'tests');

    $location = $provider->geocodeQuery(GeocodeQuery::create('Berliner Hütte'))->first();

    expect($location)->toBeInstanceOf(NominatimAddress::class)
        ->and($location->getDisplayName())->toStartWith('Berliner Hütte')
        ->and(LocationPayload::fromLocation($location)['label'])
        ->toBe('Berliner Hütte, Zemmgrund, Mayrhofen, Tirol, Österreich');
});
