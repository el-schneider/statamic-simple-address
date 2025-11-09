<?php

return [
    'default_provider' => env('SIMPLE_ADDRESS_PROVIDER', 'nominatim'),

    'providers' => [
        'nominatim' => [
            'base_url' => env('NOMINATIM_BASE_URL', 'https://nominatim.openstreetmap.org/search'),
            'freeform_search_key' => 'q',
            'request_options' => [
                'addressdetails' => '1',
                'namedetails' => '1',
                'format' => 'json',
            ],
        ],

        'geoapify' => [
            'base_url' => env('GEOAPIFY_BASE_URL', 'https://api.geoapify.com/v1/geocode/search'),
            'api_key' => env('GEOAPIFY_API_KEY'),
            'api_key_param_name' => 'apiKey',
            'freeform_search_key' => 'text',
            'request_options' => [
                'addressdetails' => '1',
                'namedetails' => '1',
                'format' => 'json',
            ],
        ],

        'geocodify' => [
            'base_url' => env('GEOCODIFY_BASE_URL', 'https://api.geocodify.com/v2/geocode'),
            'api_key' => env('GEOCODIFY_API_KEY'),
            'api_key_param_name' => 'api_key',
            'freeform_search_key' => 'q',
            'request_options' => [
                'addressdetails' => '1',
                'namedetails' => '1',
                'format' => 'json',
            ],
        ],
    ],
];
