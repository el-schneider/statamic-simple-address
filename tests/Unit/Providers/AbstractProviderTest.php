<?php

use ElSchneider\StatamicSimpleAddress\Data\SearchResponse;
use ElSchneider\StatamicSimpleAddress\Providers\AbstractProvider;

it('excludes specified fields when normalizing', function () {
    $provider = new class extends AbstractProvider
    {
        protected string $baseUrl = 'https://example.com';

        protected array $defaultExcludeFields = ['exclude_me', 'also_exclude'];

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
                $this->normalize($rawResponse[0]),
            ]);
        }
    };

    $item = [
        'label' => 'Test Place',
        'lat' => '51.5',
        'lon' => '-0.1',
        'type' => 'city',
        'name' => 'Test',
        'address' => ['city' => 'London'],
        'exclude_me' => 'should_be_removed',
        'also_exclude' => 'also_removed',
        'keep_me' => 'should_stay',
    ];

    $result = $provider->transformResponse([$item]);
    $resultArray = $result->results[0]->toArray();

    expect($resultArray)->not->toHaveKey('exclude_me')
        ->and($resultArray)->not->toHaveKey('also_exclude')
        ->and($resultArray['additional']['keep_me'])->toBe('should_stay');
});

it('merges setExcludeFields with defaults', function () {
    $provider = new class extends AbstractProvider
    {
        protected string $baseUrl = 'https://example.com';

        protected array $defaultExcludeFields = ['default_exclude'];

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
            return new SearchResponse([]);
        }
    };

    $provider->setExcludeFields(['additional_exclude']);
    $fields = $provider->getExcludeFields();

    expect($fields)->toContain('default_exclude')
        ->and($fields)->toContain('additional_exclude');
});
