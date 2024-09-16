<?php

namespace Lakasir\LakasirModule\Providers;

use Illuminate\Support\ServiceProvider;
use ReflectionClass;

class ExtendModuleServiceProvider extends ServiceProvider
{
    public function register()
    {
        $reflector = new ReflectionClass($this);

        $moduleName = str($reflector->getNamespaceName())->after('Modules\\');
        $dir = str($reflector->getFileName())->before($reflector->getShortName().'.php');

        $this->mergeConfigFrom($dir.'/../config/config.php', str($moduleName)->snake('-')->value());
        $this->loadViewsFrom($dir.'/../resources/views', $moduleName->value());
        $this->loadMigrationsFrom($dir.'/../database/migrations');
    }
}
