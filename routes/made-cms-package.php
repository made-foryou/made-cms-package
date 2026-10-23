<?php

declare(strict_types=1);

use Illuminate\Http\Middleware\SetCacheHeaders;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Route;
use Made\CmsPackage\Http\Controllers\Access\LauncherAssetController;
use Made\CmsPackage\Http\Controllers\Access\LoginCodeController;
use Made\CmsPackage\Http\Controllers\Access\LoginEmailController;
use Made\CmsPackage\Http\Controllers\Access\LoginLinkController;
use Made\CmsPackage\Http\Controllers\Access\SessionController;
use Made\CmsPackage\Http\Middleware\SetLauncherLocale;

/** @var string $prefix */
$prefix = config('made-cms-package.routes.prefix');

/** @var list<string> $middleware */
$middleware = config('made-cms-package.routes.middleware');

Route::prefix($prefix)
    ->middleware($middleware)
    ->name('made-cms-package.')
    ->group(function (): void {
        // The launcher talks to these over fetch; the answers differ per browser, so nothing may cache them.
        Route::middleware([SetLauncherLocale::class, SetCacheHeaders::class.':no_store;private'])->group(function (): void {
            Route::get('session', SessionController::class)->name('session');
            Route::post('login/email', LoginEmailController::class)->name('login.email');
            Route::post('login/code', LoginCodeController::class)
                ->middleware(ThrottleRequests::using('made-cms-package.login-code'))
                ->name('login.code');
        });

        Route::middleware(SetLauncherLocale::class)->group(function (): void {
            Route::get('login/link/{token}', LoginLinkController::class)->name('login.link');
        });
    });

// Static launcher assets load on every host page, so they run without middleware: no session, no cookies.
Route::prefix($prefix)
    ->name('made-cms-package.')
    ->group(function (): void {
        Route::get('launcher/{asset}', LauncherAssetController::class)->name('launcher.asset');
    });
