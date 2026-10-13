<?php

declare(strict_types=1);

namespace Made\CmsPackage\Tests;

use Illuminate\Foundation\Application;
use Lorisleiva\Actions\ActionServiceProvider;
use Made\CmsPackage\CmsPackageServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    /**
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            ActionServiceProvider::class,
            CmsPackageServiceProvider::class,
        ];
    }

    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);

        $app['config']->set('app.locale', 'nl');
        $app['config']->set('app.fallback_locale', 'en');

        $app['config']->set('hashing.bcrypt.rounds', 4);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}
