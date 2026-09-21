<?php

use ElSchneider\StatamicSimpleAddress\Fieldtypes\SimpleAddress;

it('preloads OpenStreetMap tiles by default', function () {
    $tiles = (new SimpleAddress)->preload()['tiles'];

    expect($tiles['light']['url'])
        ->toBe('https://tile.openstreetmap.org/{z}/{x}/{y}.png')
        ->and($tiles['dark'])
        ->toBeNull();
});

it('falls back to the bundled map tiles when an older cached config has no tile settings', function () {
    config()->set('simple-address.map.tiles', null);

    $tiles = (new SimpleAddress)->preload()['tiles'];

    expect($tiles['light']['url'])
        ->toBe('https://tile.openstreetmap.org/{z}/{x}/{y}.png')
        ->and($tiles['dark'])
        ->toBeNull();
});
