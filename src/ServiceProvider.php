<?php

namespace ElSchneider\StatamicSimpleAddress;

use ElSchneider\StatamicSimpleAddress\Fieldtypes\SimpleAddress;
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

    public function boot()
    {
        parent::boot();

        $this->publishes([
            __DIR__.'/../config/simple-address.php' => config_path('simple-address.php'),
        ], 'simple-address-config');
    }
}
