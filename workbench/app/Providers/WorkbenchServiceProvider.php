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
        // The workbench keeps its strip types and page types in workbench/app/Cms instead of the app folder of a site.
        config([
            'made-cms-package.strips.path' => dirname(__DIR__).'/Cms/Strips',
            'made-cms-package.strips.namespace' => 'Workbench\App\Cms\Strips',
            'made-cms-package.page_types.path' => dirname(__DIR__).'/Cms/PageTypes',
            'made-cms-package.page_types.namespace' => 'Workbench\App\Cms\PageTypes',
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
