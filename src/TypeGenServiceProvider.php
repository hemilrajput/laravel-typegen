<?php

namespace hemilrajput\TypeGen;

use hemilrajput\TypeGen\Commands\GenerateCommand;
use Illuminate\Support\ServiceProvider;

class TypeGenServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/typegen.php', 'typegen');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/typegen.php' => config_path('typegen.php'),
            ], 'typegen-config');

            $this->commands([
                GenerateCommand::class,
            ]);
        }
    }
}
