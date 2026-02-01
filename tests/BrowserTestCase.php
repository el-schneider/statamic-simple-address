<?php

namespace Tests;

use ElSchneider\StatamicSimpleAddress\ServiceProvider;
use Statamic\Testing\AddonTestCase;

abstract class BrowserTestCase extends AddonTestCase
{
    protected string $addonName = 'el-schneider/statamic-simple-address';

    protected string $addonServiceProvider = ServiceProvider::class;

    private string $browserTestDirectory;

    protected function setUp(): void
    {
        $this->browserTestDirectory = sys_get_temp_dir().'/statamic-simple-address-browser/'.uniqid('', true);

        parent::setUp();

        $this->withVite();
    }

    protected function tearDown(): void
    {
        \Illuminate\Support\Facades\File::deleteDirectory($this->browserTestDirectory);

        parent::tearDown();
    }

    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('statamic.stache.stores.taxonomies.directory', $this->browserTestDirectory.'/content/taxonomies');
        $app['config']->set('statamic.stache.stores.terms.directory', $this->browserTestDirectory.'/content/taxonomies');
        $app['config']->set('statamic.stache.stores.collections.directory', $this->browserTestDirectory.'/content/collections');
        $app['config']->set('statamic.stache.stores.entries.directory', $this->browserTestDirectory.'/content/collections');
        $app['config']->set('statamic.stache.stores.navigation.directory', $this->browserTestDirectory.'/content/navigation');
        $app['config']->set('statamic.stache.stores.globals.directory', $this->browserTestDirectory.'/content/globals');
        $app['config']->set('statamic.stache.stores.global-variables.directory', $this->browserTestDirectory.'/content/globals');
        $app['config']->set('statamic.stache.stores.asset-containers.directory', $this->browserTestDirectory.'/content/assets');
        $app['config']->set('statamic.stache.stores.nav-trees.directory', $this->browserTestDirectory.'/content/structures/navigation');
        $app['config']->set('statamic.stache.stores.collection-trees.directory', $this->browserTestDirectory.'/content/structures/collections');
        $app['config']->set('statamic.stache.stores.form-submissions.directory', $this->browserTestDirectory.'/content/submissions');
        $app['config']->set('statamic.stache.stores.users.directory', $this->browserTestDirectory.'/users');

        \Statamic\Facades\Blueprint::setDirectory($this->browserTestDirectory.'/blueprints');
    }

    protected function resolveApplicationConfiguration($app)
    {
        parent::resolveApplicationConfiguration($app);

        $app['config']['statamic.editions.pro'] = true;

        $app['config']->set('auth.providers.statamic', [
            'driver' => 'statamic',
        ]);
        $app['config']->set('auth.guards.web.provider', 'statamic');
        $app['config']->set('auth.passwords.users.provider', 'statamic');

        $app['config']->set('statamic.users.repository', 'file');
        $app['config']->set('statamic.stache.watcher', false);

        // Load the addon config
        $config = require __DIR__.'/../config/simple-address.php';

        // Use stub provider in tests to avoid external API calls
        $config['provider'] = 'stub';
        $config['providers']['stub'] = [
            'class' => \Tests\Stubs\StubProvider::class,
        ];

        $app['config']->set('simple-address', $config);
    }
}
