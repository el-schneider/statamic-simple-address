<?php

return [
    // Which provider to use by default
    'provider' => env('SIMPLE_ADDRESS_PROVIDER', 'nominatim'),

    // Cache configuration
    'cache' => [
        'enabled' => env('SIMPLE_ADDRESS_CACHE_ENABLED', true),
        'store' => env('SIMPLE_ADDRESS_CACHE_STORE', null),
        'duration' => env('SIMPLE_ADDRESS_CACHE_DURATION', 9999999),
    ],

    // Available providers and their configuration
    'providers' => [
        'nominatim' => [
            'class' => \Geocoder\Provider\Nominatim\Nominatim::class,
            'factory' => 'withOpenStreetMapServer',
            'args' => [
                config('app.name', 'Statamic Simple Address'),
            ],
        ],
    ],
];
