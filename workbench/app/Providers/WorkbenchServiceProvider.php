<?php

declare(strict_types=1);

namespace Workbench\App\Providers;

use Illuminate\Support\ServiceProvider;

class WorkbenchServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // The workbench keeps its strip types in workbench/app/Cms/Strips instead of the app folder of a site.
        config([
            'made-cms-package.strips.path' => dirname(__DIR__).'/Cms/Strips',
            'made-cms-package.strips.namespace' => 'Workbench\App\Cms\Strips',
        ]);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
