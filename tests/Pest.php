<?php

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(Tests\TestCase::class)
    ->in('Unit', 'Feature');

pest()->extend(Tests\BrowserTestCase::class)
    ->group('browser')
    ->beforeEach(function () {
        $statamicCpManifestPath = public_path('vendor/statamic/cp/build/manifest.json');

        if (! file_exists($statamicCpManifestPath)) {
            $source = dirname(__DIR__).'/vendor/statamic/cms/resources/dist/build';
            $target = public_path('vendor/statamic/cp/build');

            \Illuminate\Support\Facades\File::ensureDirectoryExists($target);
            \Illuminate\Support\Facades\File::copyDirectory($source, $target);
        }

        \Illuminate\Support\Facades\Artisan::call('vendor:publish', [
            '--tag' => 'statamic-simple-address',
            '--force' => true,
        ]);
    })
    ->in('Browser');

pest()->browser()->timeout(10000);

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/
