<?php

namespace Hemilrajput\TypeGen\Tests;

use Hemilrajput\TypeGen\TypeGenServiceProvider;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
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

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('users', function (Blueprint $blueprint): void {
            $blueprint->id();
            $blueprint->string('name');
            $blueprint->string('email')->unique();
            $blueprint->string('role')->nullable();
            $blueprint->string('password');
            $blueprint->string('remember_token')->nullable();
            $blueprint->timestamp('email_verified_at')->nullable();
            $blueprint->boolean('is_admin')->default(false);
            $blueprint->json('preferences')->nullable();
            $blueprint->string('status')->nullable();
            $blueprint->timestamps();
        });

        Schema::create('posts', function (Blueprint $blueprint): void {
            $blueprint->id();
            $blueprint->foreignId('user_id');
            $blueprint->string('title');
            $blueprint->text('body');
            $blueprint->timestamps();
        });

        Schema::create('profiles', function (Blueprint $blueprint): void {
            $blueprint->id();
            $blueprint->foreignId('user_id');
            $blueprint->string('bio')->nullable();
            $blueprint->timestamps();
        });

        Schema::create('ignored_users', function (Blueprint $blueprint): void {
            $blueprint->id();
            $blueprint->string('name');
            $blueprint->timestamps();
        });
    }
}
