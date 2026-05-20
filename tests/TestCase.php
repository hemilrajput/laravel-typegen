<?php

namespace hemilrajput\TypeGen\Tests;

use hemilrajput\TypeGen\TypeGenServiceProvider;
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
    }
}
