<?php

namespace Lakasir\LakasirModule\Tests;

use Lakasir\LakasirModule\LakasirModuleServiceProvider;
use Orchestra\Testbench\TestCase as TestbenchTestCase;

abstract class TestCase extends TestbenchTestCase
{
    protected function getPackageProviders($app): array
    {
        return [LakasirModuleServiceProvider::class];
    }
}
