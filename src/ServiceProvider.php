<?php

namespace ElSchneider\StatamicSimpleAddress;

use ElSchneider\StatamicSimpleAddress\Fieldtypes\SimpleAddress;
use ElSchneider\StatamicSimpleAddress\Http\Controllers\GeocodingController;
use ElSchneider\StatamicSimpleAddress\Services\GeocodingService;
use Illuminate\Support\Facades\Route;
use Statamic\Providers\AddonServiceProvider;

class ServiceProvider extends AddonServiceProvider
{
    protected $fieldtypes = [
        SimpleAddress::class,
    ];

    protected $vite = [
        'input' => [
            'resources/js/simple-address.js',
        ],
        'publicDirectory' => 'resources/dist',
    ];

    public function register()
    {
        parent::register();

        $this->app->singleton(GeocodingService::class);
    }

    public function bootAddon()
    {
        $this->registerCpRoutes(function () {
            Route::post('simple-address/search', [GeocodingController::class, 'search'])
                ->name('simple-address.search');
            Route::post('simple-address/reverse', [GeocodingController::class, 'reverse'])
                ->name('simple-address.reverse');
        });
    }

    public function boot()
    {
        parent::boot();

        $this->publishes([
            __DIR__.'/../config/simple-address.php' => config_path('simple-address.php'),
        ], 'simple-address-config');
    }
}
