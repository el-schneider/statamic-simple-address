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

    public function boot(): void
    {
        parent::boot();

        $this->publishes([
            __DIR__.'/../resources/dist/build' => public_path('vendor/statamic-simple-address/build'),
        ], 'statamic-simple-address-assets');

        $this->registerConfiguration();
        $this->registerServices();
        $this->registerRoutes();
    }

    /**
     * Register configuration files and publish paths.
     */
    private function registerConfiguration(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/simple-address.php', 'simple-address');

        $this->publishes([
            __DIR__.'/../config/simple-address.php' => config_path('simple-address.php'),
        ], 'simple-address-config');
    }

    /**
     * Register all core services in the container.
     */
    private function registerServices(): void
    {
        $this->app->singleton(GeocodingService::class);
    }

    /**
     * Register CP routes for geocoding endpoints.
     */
    private function registerRoutes(): void
    {
        $this->registerCpRoutes(function () {
            Route::post('simple-address/search', [GeocodingController::class, 'search'])
                ->name('simple-address.search');
            Route::post('simple-address/reverse', [GeocodingController::class, 'reverse'])
                ->name('simple-address.reverse');
        });
    }
}
