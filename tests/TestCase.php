<?php

namespace Tests;

use ElSchneider\StatamicSimpleAddress\ServiceProvider;
use Statamic\Testing\AddonTestCase;

abstract class TestCase extends AddonTestCase
{
    protected string $addonName = 'el-schneider/statamic-simple-address';

    protected string $addonServiceProvider = ServiceProvider::class;

    protected function resolveApplicationConfiguration($app)
    {
        parent::resolveApplicationConfiguration($app);

        $app['config']['statamic.editions.pro'] = true;
        $app['config']['app.key'] = 'base64:'.base64_encode(random_bytes(32));

        // Load the addon config
        $app['config']->set('simple-address', require __DIR__.'/../config/simple-address.php');
    }
}
