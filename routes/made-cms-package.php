<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Made\CmsPackage\Http\Controllers\Access\LauncherAssetController;

/** @var string $prefix */
$prefix = config('made-cms-package.routes.prefix');

/** @var list<string> $middleware */
$middleware = config('made-cms-package.routes.middleware');

Route::prefix($prefix)
    ->middleware($middleware)
    ->name('made-cms-package.')
    ->group(function (): void {
        // CMS routes are added here together with their first Http feature.
    });

// Static launcher assets load on every host page, so they run without middleware: no session, no cookies.
Route::prefix($prefix)
    ->name('made-cms-package.')
    ->group(function (): void {
        Route::get('launcher/{asset}', LauncherAssetController::class)->name('launcher.asset');
    });
