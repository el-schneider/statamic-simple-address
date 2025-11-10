<?php

use ElSchneider\StatamicSimpleAddress\Data\AddressResult;
use ElSchneider\StatamicSimpleAddress\Data\SearchResponse;
use ElSchneider\StatamicSimpleAddress\Transformers\Transformer;

test('normalize excludes specified fields', function () {
    $transformer = new class extends Transformer {
        public function transform(array $rawResponse): SearchResponse
        {
            return new SearchResponse([
                $this->normalize($rawResponse[0], ['exclude_me', 'also_exclude']),
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

    $result = $transformer->transform([$item]);
    $resultArray = $result->results[0]->toArray();

    expect($resultArray)->not->toHaveKey('exclude_me')
        ->and($resultArray)->not->toHaveKey('also_exclude')
        ->and($resultArray['additional']['keep_me'])->toBe('should_stay');
});
