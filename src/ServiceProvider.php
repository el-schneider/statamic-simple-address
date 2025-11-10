<?php

namespace ElSchneider\StatamicSimpleAddress;

use ElSchneider\StatamicSimpleAddress\Fieldtypes\SimpleAddress;
use ElSchneider\StatamicSimpleAddress\Http\Controllers\AddressSearchController;
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

    public function bootAddon()
    {
        $this->registerCpRoutes(function () {
            Route::post('simple-address/search', AddressSearchController::class)
                ->name('simple-address.search');
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
