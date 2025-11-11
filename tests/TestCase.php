<?php

namespace Tests;

use ElSchneider\StatamicSimpleAddress\ServiceProvider;
use Statamic\Testing\AddonTestCase;
use Statamic\Testing\Concerns\PreventsSavingStacheItemsToDisk;

abstract class TestCase extends AddonTestCase
{
    use PreventsSavingStacheItemsToDisk;

    protected string $addonName = 'el-schneider/statamic-simple-address';

    protected string $addonServiceProvider = ServiceProvider::class;

    protected function resolveApplicationConfiguration($app)
    {
        parent::resolveApplicationConfiguration($app);

        $app['config']['statamic.editions.pro'] = true;

        // Load the addon config
        $app['config']->set('simple-address', require __DIR__.'/../config/simple-address.php');
    }

    protected function deleteFakeStacheDirectory(): void
    {
        app('files')->deleteDirectory($this->fakeStacheDirectory);

        if (! is_dir($this->fakeStacheDirectory)) {
            mkdir($this->fakeStacheDirectory, 0755, true);
        }

        touch($this->fakeStacheDirectory.'/.gitkeep');
    }
}
