<?php

namespace Lakasir\LakasirModule;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Lakasir\LakasirModule\Skeleton\SkeletonClass
 */
class LakasirModuleFacade extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'lakasir-module';
    }
}
