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

    'map' => [
        'tiles' => [
            'light' => [
                'url' => 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
                'options' => [
                    'attribution' => '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
                    'maxNativeZoom' => 19,
                    'maxZoom' => 20,
                ],
            ],
            'dark' => null,
            // Set CARTO_API_KEY before enabling this example.
            // 'dark' => [
            //     'url' => 'https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png?key={key}',
            //     'options' => [
            //         'key' => env('CARTO_API_KEY'),
            //         'subdomains' => 'abcd',
            //         'maxZoom' => 20,
            //         'attribution' => '&copy; OpenStreetMap contributors &copy; CARTO',
            //     ],
            // ],
        ],
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
        // 'mapbox' => [
        //     'class' => \Geocoder\Provider\Mapbox\Mapbox::class,
        //     'args' => [
        //         env('MAPBOX_ACCESS_TOKEN'),
        //     ],
        // ],
        // 'google' => [
        //     'class' => \Geocoder\Provider\GoogleMaps\GoogleMaps::class,
        //     'args' => [
        //         'apiKey' => env('GOOGLE_GEOCODE_API_KEY'),
        //     ],
        // ],
    ],
];
