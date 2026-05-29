<?php

namespace Hemilrajput\TypeGen\Tests;

use Hemilrajput\TypeGen\TypeGenServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [TypeGenServiceProvider::class];
    }

    protected function defineEnvironment($app)
    {
        // Setup default config for tests
        $app['config']->set('typegen', include __DIR__.'/../config/typegen.php');

        // Setup default database to use sqlite in memory
        $app['config']->set('database.default', 'testbench');
        $app['config']->set('database.connections.testbench', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }
}
