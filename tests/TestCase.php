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
    }
}
