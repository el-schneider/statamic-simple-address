<?php

return [
    /*
     * Default geocoding provider for all Simple Address fields.
     * Can be overridden per field in the field configuration.
     */
    'default_provider' => env('SIMPLE_ADDRESS_PROVIDER', 'nominatim'),

    'providers' => [
        'nominatim' => [
            // Free OpenStreetMap geocoding. No API key required.
            // Rate limit: 1 request/second. Use debounce delay >= 1000ms.
            // Docs: https://nominatim.org/
            'base_url' => env('NOMINATIM_BASE_URL', 'https://nominatim.openstreetmap.org/search'),
            'freeform_search_key' => 'q',
            'request_options' => [
                'addressdetails' => '1',
                'namedetails' => '1',
                'format' => 'json',
            ],
            'display_field' => 'display_name',
            'results_path' => '$[*]',
        ],

        'geoapify' => [
            // Managed Nominatim service with higher reliability and better support.
            // Requires API key: https://www.geoapify.com/
            'base_url' => env('GEOAPIFY_BASE_URL', 'https://api.geoapify.com/v1/geocode/search'),
            'api_key' => env('GEOAPIFY_API_KEY'),
            'api_key_param_name' => 'apiKey',
            'freeform_search_key' => 'text',
            'request_options' => [
                'addressdetails' => '1',
                'namedetails' => '1',
                'format' => 'json',
            ],
            'display_field' => 'formatted',
            'results_path' => '$.results[*]',
        ],

        'geocodify' => [
            // Alternative geocoding provider with global coverage.
            // Requires API key: https://geocodify.com/
            'base_url' => env('GEOCODIFY_BASE_URL', 'https://api.geocodify.com/v2/geocode'),
            'api_key' => env('GEOCODIFY_API_KEY'),
            'api_key_param_name' => 'api_key',
            'freeform_search_key' => 'q',
            'request_options' => [
                'addressdetails' => '1',
                'namedetails' => '1',
                'format' => 'json',
            ],
            'display_field' => 'label',
            'results_path' => '$.response.features[*].properties',
        ],

        /*
         * Custom providers can be added here. Example:
         *
         * 'my_provider' => [
         *     'base_url' => env('MY_PROVIDER_BASE_URL'),
         *     'api_key' => env('MY_PROVIDER_API_KEY'),
         *     'api_key_param_name' => 'api_key',
         *     'freeform_search_key' => 'q',
         *     'request_options' => [
         *         'format' => 'json',
         *     ],
         *     'display_field' => 'name',
         *     'results_path' => '$.results',
         * ],
         */
    ],
];
