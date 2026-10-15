<?php

declare(strict_types=1);

namespace Made\CmsPackage;

use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Foundation\Http\Kernel;
use Illuminate\Support\ServiceProvider;
use Made\CmsPackage\Http\Middleware\InjectLauncher;

class CmsPackageServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/made-cms-package.php', 'made-cms-package');
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../routes/made-cms-package.php');

        $this->loadViewsFrom(__DIR__.'/../resources/views', 'made-cms-package');

        // The launcher markup belongs to its package-served script and styles, so it is never published.
        $this->loadViewsFrom(__DIR__.'/../resources/launcher', 'made-cms-package-launcher');

        $this->loadTranslationsFrom(__DIR__.'/../lang', 'made-cms-package');

        // Unconditional, because the kernel only resolves for HTTP requests. Appending to the kernel group
        // (instead of the router) survives every later sync of the middleware groups to the router.
        $this->callAfterResolving(HttpKernel::class, function (mixed $kernel): void {
            if ($kernel instanceof Kernel && array_key_exists('web', $kernel->getMiddlewareGroups())) {
                $kernel->appendMiddlewareToGroup('web', InjectLauncher::class);
            }
        });

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
    }
}
