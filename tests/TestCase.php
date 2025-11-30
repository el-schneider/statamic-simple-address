<?php

namespace Tests;

use ElSchneider\StatamicSimpleAddress\ServiceProvider;
use Statamic\Facades\User;
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
        $config = require __DIR__.'/../config/simple-address.php';

        // Use stub provider in tests to avoid external API calls
        $config['provider'] = 'stub';
        $config['providers']['stub'] = [
            'class' => \Tests\Stubs\StubProvider::class,
        ];

        $app['config']->set('simple-address', $config);
    }

    protected function deleteFakeStacheDirectory(): void
    {
        app('files')->deleteDirectory($this->fakeStacheDirectory);

        if (! is_dir($this->fakeStacheDirectory)) {
            mkdir($this->fakeStacheDirectory, 0755, true);
        }

        touch($this->fakeStacheDirectory.'/.gitkeep');
    }

    public function actingAsSuperAdmin()
    {
        $admin = User::make()
            ->email('admin@test.com')
            ->makeSuper();

        $admin->save();

        return $this->actingAs($admin);
    }
}
