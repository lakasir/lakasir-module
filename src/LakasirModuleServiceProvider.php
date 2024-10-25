<?php

namespace Lakasir\LakasirModule;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Lakasir\LakasirModule\Console\Commands\MakeController;
use Lakasir\LakasirModule\Console\Commands\MakeMigration;
use Lakasir\LakasirModule\Console\Commands\MakeModel;
use Lakasir\LakasirModule\Console\Commands\MakeModuleFilamentRelationManager;
use Lakasir\LakasirModule\Console\Commands\MakeModuleFilamentResource;
use Lakasir\LakasirModule\Console\Commands\Migrate;
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
                MakeModuleFilamentRelationManager::class,
                MakeModel::class,
                MakeController::class,
                MakeMigration::class,
                Migrate::class,
            ]);
        }
    }

    public function register()
    {
        $modules = File::isDirectory(base_path('modules')) ? File::directories(base_path('modules')) : [];

        foreach ($modules as $module) {
            // Load the module's autoload file (if it exists)
            $this->autoloadModuleDependencies($module);

            // Get and register the module's service provider
            $provider = $this->getModuleServiceProvider($module);
            if ($provider) {
                $this->app->register($provider);

                $this->loadModuleRoute($module);
            }
        }
    }

    protected function autoloadModuleDependencies($modulePath)
    {
        $vendorAutoload = $modulePath.'/vendor/autoload.php';
        if (file_exists($vendorAutoload)) {
            require_once $vendorAutoload;
        }
    }

    protected function getModuleServiceProvider($modulePath)
    {
        $moduleName = basename($modulePath);
        $providerClass = "Modules\\{$moduleName}\\{$moduleName}ServiceProvider";

        return class_exists($providerClass) ? $providerClass : null;
    }

    private function loadModuleRoute($module)
    {
        $routes = File::files($module.'/routes');
        foreach ($routes as $route) {
            $routeFile = $route->getPathname();

            if (file_exists($routeFile)) {
                Route::prefix('modules/'.str(basename($module))->snake('-'))
                    ->middleware([
                        'web',
                    ])
                    ->group($routeFile);
            }
        }
    }
}
