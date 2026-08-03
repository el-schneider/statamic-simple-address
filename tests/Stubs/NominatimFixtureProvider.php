<?php

namespace Tests\Stubs;

use Geocoder\Collection;
use Geocoder\Model\AddressCollection;
use Geocoder\Model\Coordinates;
use Geocoder\Provider\Nominatim\Nominatim;
use Geocoder\Provider\Provider;
use Geocoder\Query\GeocodeQuery;
use Geocoder\Query\ReverseQuery;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;

/**
 * Runs recorded Nominatim responses through the real provider, so tests cover the
 * actual parsing instead of a hand-built model.
 */
class NominatimFixtureProvider implements Provider
{
    public static string $fixture = 'search-peak';

    public static int $calls = 0;

    /**
     * @return array<int, \Geocoder\Location>
     */
    public static function locations(string $fixture): array
    {
        $body = file_get_contents(__DIR__.'/../__fixtures__/nominatim/'.$fixture.'.json');
        $handler = HandlerStack::create(new MockHandler([new Response(200, [], $body)]));
        $provider = Nominatim::withOpenStreetMapServer(new Client(['handler' => $handler]), 'tests');

        return str_starts_with($fixture, 'reverse-')
            ? $provider->reverseQuery(ReverseQuery::create(new Coordinates(0, 0)))->all()
            : $provider->geocodeQuery(GeocodeQuery::create('fixture'))->all();
    }

    public function geocodeQuery(GeocodeQuery $query): Collection
    {
        self::$calls++;

        return new AddressCollection(self::locations(self::$fixture));
    }

    public function reverseQuery(ReverseQuery $query): Collection
    {
        self::$calls++;

        return new AddressCollection(self::locations(self::$fixture));
    }

    public function getName(): string
    {
        return 'nominatim';
    }
}
