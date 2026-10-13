<?php

declare(strict_types=1);

namespace Made\CmsPackage;

use Illuminate\Support\ServiceProvider;
use Made\CmsPackage\Console\Commands\CmsPackageCommand;

class CmsPackageServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/made-cms-package.php', 'made-cms-package');

        $this->app->singleton(CmsPackage::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../routes/made-cms-package.php');

        $this->loadViewsFrom(__DIR__.'/../resources/views', 'made-cms-package');

        $this->loadTranslationsFrom(__DIR__.'/../lang', 'made-cms-package');

        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/made-cms-package.php' => config_path('made-cms-package.php'),
        ], ['made-cms-package', 'made-cms-package-config']);

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/made-cms-package'),
        ], ['made-cms-package', 'made-cms-package-views']);

        $this->publishes([
            __DIR__.'/../lang' => $this->app->langPath('vendor/made-cms-package'),
        ], ['made-cms-package', 'made-cms-package-lang']);

        $this->publishes([
            __DIR__.'/../public' => public_path('vendor/made-cms-package'),
        ], ['made-cms-package', 'made-cms-package-assets']);

        $this->publishesMigrations([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], ['made-cms-package', 'made-cms-package-migrations']);

        $this->commands([
            CmsPackageCommand::class,
        ]);
    }
}
