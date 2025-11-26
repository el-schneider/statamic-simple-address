<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Geocoding Provider
    |--------------------------------------------------------------------------
    |
    | The default provider used for all Simple Address fields.
    | Can be overridden per field in the field configuration.
    |
    | Supported: "nominatim", "geoapify", "google", "mapbox"
    |
    */
    'default_provider' => env('SIMPLE_ADDRESS_PROVIDER', 'nominatim'),

    /*
    |--------------------------------------------------------------------------
    | Providers
    |--------------------------------------------------------------------------
    |
    | Configure geocoding providers. Each provider specifies a class that
    | handles request building, response transformation, and API specifics.
    |
    | Built-in providers work with zero config. Just set your API keys in .env:
    |   - nominatim: Free, no key required (rate limited to 1 req/sec)
    |   - geoapify: GEOAPIFY_API_KEY
    |   - google: GOOGLE_GEOCODE_API_KEY
    |   - mapbox: MAPBOX_ACCESS_TOKEN
    |
    | Override any default by adding config here. All keys are optional:
    |   'nominatim' => [
    |       'base_url' => 'https://my-nominatim-instance.com/search',
    |   ],
    |
    | Custom providers:
    |   'my_provider' => [
    |       'class' => App\Providers\MyGeoProvider::class,
    |       'api_key' => env('MY_PROVIDER_API_KEY'),
    |   ],
    |
    */
    'providers' => [
        'nominatim' => [],
        'geoapify' => [],
        'google' => [],
        'mapbox' => [],
    ],
];
