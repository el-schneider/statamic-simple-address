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
}
