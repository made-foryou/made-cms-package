<?php

declare(strict_types=1);

namespace Made\CmsPackage\Tests;

use Illuminate\Database\Migrations\Migrator;
use Illuminate\Foundation\Application;
use Lorisleiva\Actions\ActionServiceProvider;
use Made\CmsPackage\CmsPackageServiceProvider;
use Made\CmsPackage\Tests\Fixtures\Database\TestDatabase;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    /**
     * A database folder of its own, for a test that publishes into it: tests running at the same time never share one.
     * The application takes it when it is (re)created.
     */
    public static ?string $databasePath = null;

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
        if (self::$databasePath !== null) {
            $app->useDatabasePath(self::$databasePath);
        }

        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', TestDatabase::connection($app['config']));

        // Only where the migrations are: RefreshDatabase runs them once per database, and a test without it needs no
        // tables. Testbench's loadMigrationsFrom() would migrate and roll back around tests on a server database.
        $app->afterResolving('migrator', static function (Migrator $migrator): void {
            $migrator->path(__DIR__.'/../database/migrations');
        });

        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('m', 32)));

        $app['config']->set('app.locale', 'nl');
        $app['config']->set('app.fallback_locale', 'en');

        $app['config']->set('hashing.bcrypt.rounds', 4);

        // Rate limiters and sessions in memory, whatever the workbench .env says.
        $app['config']->set('cache.default', 'array');
        $app['config']->set('session.driver', 'array');
        $app['config']->set('mail.default', 'array');
    }

    /**
     * Testbench runs this before RefreshDatabase migrates, so the database of the tests is there by then.
     */
    protected function defineDatabaseMigrations(): void
    {
        TestDatabase::create($this->app['db'], $this->app['config']);
    }
}
