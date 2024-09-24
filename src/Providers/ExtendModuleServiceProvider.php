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

        $snakeCaseName = str($moduleName)->snake('-')->value();
        $this->mergeConfigFrom("$dir/../config/{$snakeCaseName}.php", $snakeCaseName);
        $this->loadViewsFrom($dir.'/../resources/views', $moduleName->value());
        // $this->loadTranslationsFrom($dir.'/../lang/', $moduleName->value());
    }
}
