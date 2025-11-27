<?php

use ElSchneider\StatamicSimpleAddress\Data\SearchResponse;
use ElSchneider\StatamicSimpleAddress\Providers\AbstractProvider;

it('creates result with flat data structure', function () {
    $provider = new class extends AbstractProvider
    {
        protected string $baseUrl = 'https://example.com';

        public function buildSearchRequest(string $query, array $options = []): array
        {
            return ['url' => $this->baseUrl, 'params' => []];
        }

        public function buildReverseRequest(float $lat, float $lon, array $options = []): array
        {
            return ['url' => $this->baseUrl, 'params' => []];
        }

        public function transformResponse(array $rawResponse): SearchResponse
        {
            return new SearchResponse([
                $this->createResult(
                    label: 'Hauptstraße 10, 10115 Berlin, Germany',
                    lat: '52.52',
                    lon: '13.405',
                    data: [
                        'id' => 'abc123',
                        'name' => 'Hauptstraße 10',
                        'city' => 'Berlin',
                        'region' => 'Berlin',
                        'country' => 'Germany',
                        'countryCode' => 'DE',
                    ],
                ),
            ]);
        }
    };

    $result = $provider->transformResponse([])->results[0]->toArray();

    expect($result['label'])->toBe('Hauptstraße 10, 10115 Berlin, Germany')
        ->and($result['lat'])->toBe('52.52')
        ->and($result['lon'])->toBe('13.405')
        ->and($result['id'])->toBe('abc123')
        ->and($result['name'])->toBe('Hauptstraße 10')
        ->and($result['city'])->toBe('Berlin')
        ->and($result['region'])->toBe('Berlin')
        ->and($result['country'])->toBe('Germany')
        ->and($result['countryCode'])->toBe('DE');
});

it('omits null and empty values from output', function () {
    $provider = new class extends AbstractProvider
    {
        protected string $baseUrl = 'https://example.com';

        public function buildSearchRequest(string $query, array $options = []): array
        {
            return ['url' => $this->baseUrl, 'params' => []];
        }

        public function buildReverseRequest(float $lat, float $lon, array $options = []): array
        {
            return ['url' => $this->baseUrl, 'params' => []];
        }

        public function transformResponse(array $rawResponse): SearchResponse
        {
            return new SearchResponse([
                $this->createResult(
                    label: 'Some Place',
                    lat: '10.0',
                    lon: '20.0',
                    data: ['city' => 'Berlin', 'region' => null, 'country' => ''],
                ),
            ]);
        }
    };

    $result = $provider->transformResponse([])->results[0]->toArray();

    expect($result)->toHaveKey('label')
        ->and($result)->toHaveKey('lat')
        ->and($result)->toHaveKey('lon')
        ->and($result)->toHaveKey('city')
        ->and($result)->not->toHaveKey('region')
        ->and($result)->not->toHaveKey('country');
});
