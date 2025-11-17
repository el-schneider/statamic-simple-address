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
            // Rate limit: 1 request/second. Backend enforces via min_debounce_delay.
            // Docs: https://nominatim.org/
            'min_debounce_delay' => 1000,
            'base_url' => env('NOMINATIM_BASE_URL', 'https://nominatim.openstreetmap.org/search'),
            'reverse_base_url' => env('NOMINATIM_REVERSE_BASE_URL', 'https://nominatim.openstreetmap.org/reverse'),
            'freeform_search_key' => 'q',
            'request_options' => [
                'addressdetails' => '1',
                'namedetails' => '1',
                'format' => 'json',
            ],
            'reverse_request_options' => [
                'format' => 'json',
                'addressdetails' => '1',
                'zoom' => '18',
            ],
            'display_field' => 'display_name',
            'results_path' => '$[*]',
            'transformer' => \ElSchneider\StatamicSimpleAddress\Transformers\NominatimTransformer::class,
            'default_exclude_fields' => [
                'boundingbox',
                'bbox',
                'class',
                'datasource',
                'display_name',
                'icon',
                'importance',
                'licence',
                'osm_id',
                'osm_type',
                'other_names',
                'place_id',
                'rank',
            ],
        ],

        'geoapify' => [
            // Managed Nominatim service with higher reliability and better support.
            // Requires API key: https://www.geoapify.com/
            'min_debounce_delay' => 0,
            'base_url' => env('GEOAPIFY_BASE_URL', 'https://api.geoapify.com/v1/geocode/search'),
            'reverse_base_url' => env('GEOAPIFY_REVERSE_BASE_URL', 'https://api.geoapify.com/v1/geocode/reverse'),
            'api_key' => env('GEOAPIFY_API_KEY'),
            'api_key_param_name' => 'apiKey',
            'freeform_search_key' => 'text',
            'request_options' => [
                'addressdetails' => '1',
                'namedetails' => '1',
                'format' => 'json',
            ],
            'reverse_request_options' => [
                'format' => 'json',
            ],
            'display_field' => 'formatted',
            'results_path' => '$.results[*]',
            'transformer' => \ElSchneider\StatamicSimpleAddress\Transformers\GeoapifyTransformer::class,
            'default_exclude_fields' => [
                'bbox',
                'geometry',
                'place_id',
                'query',
                'rank',
                'namedetails',
            ],
        ],

        'geocodify' => [
            // Alternative geocoding provider with global coverage.
            // Requires API key: https://geocodify.com/
            'min_debounce_delay' => 0,
            'base_url' => env('GEOCODIFY_BASE_URL', 'https://api.geocodify.com/v2/geocode'),
            'reverse_base_url' => env('GEOCODIFY_REVERSE_BASE_URL', 'https://api.geocodify.com/v2/geocode'),
            'api_key' => env('GEOCODIFY_API_KEY'),
            'api_key_param_name' => 'api_key',
            'freeform_search_key' => 'q',
            'request_options' => [
                'addressdetails' => '1',
                'namedetails' => '1',
                'format' => 'json',
            ],
            'reverse_request_options' => [
                'format' => 'json',
            ],
            'display_field' => 'label',
            'results_path' => '$.response.features[*].properties',
            'transformer' => \ElSchneider\StatamicSimpleAddress\Transformers\GeocodifyTransformer::class,
            'default_exclude_fields' => [
                'bbox',
                'geometry',
                'id',
                'rank',
            ],
        ],

        'google' => [
            // Google Maps Geocoding API. Requires API key from Google Cloud Console.
            // Docs: https://developers.google.com/maps/documentation/geocoding
            'min_debounce_delay' => 0,
            'base_url' => env('GOOGLE_GEOCODE_BASE_URL', 'https://maps.googleapis.com/maps/api/geocode/json'),
            'api_key' => env('GOOGLE_GEOCODE_API_KEY'),
            'api_key_param_name' => 'key',
            'freeform_search_key' => 'address',
            'request_options' => [],
            'reverse_request_options' => [],
            'display_field' => 'formatted_address',
            'results_path' => '$.results[*]',
            'transformer' => \ElSchneider\StatamicSimpleAddress\Transformers\GoogleTransformer::class,
            'default_exclude_fields' => [
                'address_components',
                'geometry',
                'place_id',
            ],
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
