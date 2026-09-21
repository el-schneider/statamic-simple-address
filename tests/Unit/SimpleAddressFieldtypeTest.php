<?php

use ElSchneider\StatamicSimpleAddress\Fieldtypes\SimpleAddress;

it('preloads OpenStreetMap tiles when an older config has no map tiles', function () {
    config()->set('simple-address.map', []);

    $tiles = (new SimpleAddress)->preload()['tiles'];

    expect($tiles['light']['url'])
        ->toBe('https://tile.openstreetmap.org/{z}/{x}/{y}.png')
        ->and($tiles['dark'])
        ->toBeNull();
});

it('preserves custom tile URLs and arbitrary Leaflet options', function () {
    $configuredTiles = [
        'light' => [
            'url' => 'https://tiles.example.test/light/{z}/{x}/{y}.png',
            'options' => [
                'attribution' => 'Example tiles',
                'maxZoom' => 18,
                'customOption' => 'kept',
            ],
        ],
        'dark' => [
            'url' => 'https://tiles.example.test/dark/{z}/{x}/{y}.png',
            'options' => ['maxZoom' => 18],
        ],
    ];

    config()->set('simple-address.map.tiles', $configuredTiles);

    expect((new SimpleAddress)->preload()['tiles'])->toBe($configuredTiles);
});
