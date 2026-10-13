<?php

declare(strict_types=1);

namespace Made\CmsPackage\Tests;

use Made\CmsPackage\CmsPackageServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            CmsPackageServiceProvider::class,
        ];
    }
}
