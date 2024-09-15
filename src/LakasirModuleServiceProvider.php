<?php

namespace Lakasir\LakasirModule;

use Illuminate\Support\ServiceProvider;
use Lakasir\LakasirModule\Console\Commands\MakeModuleFilamentResource;
use Lakasir\LakasirModule\Console\Commands\ModuleMakeCommand;

class LakasirModuleServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     */
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/config.php' => config_path('lakasir-module.php'),
            ], 'config');

            $this->commands([
                ModuleMakeCommand::class,
                MakeModuleFilamentResource::class,
            ]);
        }
    }

    /**
     * Register the application services.
     */
    public function register()
    {
    }
}
