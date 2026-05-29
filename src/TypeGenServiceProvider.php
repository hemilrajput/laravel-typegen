<?php

namespace Hemilrajput\TypeGen;

use Hemilrajput\TypeGen\Commands\GenerateCommand;
use Hemilrajput\TypeGen\Commands\GenerateRoutesCommand;
use Hemilrajput\TypeGen\Mappers\CastTypeMapper;
use Illuminate\Support\ServiceProvider;

class TypeGenServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/typegen.php', 'typegen');

        $this->app->singleton(CastTypeMapper::class, function ($app) {
            return new CastTypeMapper(config('typegen.cast_map', []));
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/typegen.php' => config_path('typegen.php'),
            ], 'typegen-config');

            $this->commands([
                GenerateCommand::class,
                GenerateRoutesCommand::class,
            ]);
        }
    }
}
