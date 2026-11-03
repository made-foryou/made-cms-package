<?php

declare(strict_types=1);

use Illuminate\Http\Middleware\SetCacheHeaders;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Route;
use Made\CmsPackage\Access\Enums\Screen;
use Made\CmsPackage\Http\Controllers\Access\EditPageController;
use Made\CmsPackage\Http\Controllers\Access\LauncherAssetController;
use Made\CmsPackage\Http\Controllers\Access\LoginCodeController;
use Made\CmsPackage\Http\Controllers\Access\LoginCodeVerifyController;
use Made\CmsPackage\Http\Controllers\Access\LoginEmailController;
use Made\CmsPackage\Http\Controllers\Access\LoginLinkConfirmController;
use Made\CmsPackage\Http\Controllers\Access\LoginLinkController;
use Made\CmsPackage\Http\Controllers\Access\LoginPageController;
use Made\CmsPackage\Http\Controllers\Access\LogoutController;
use Made\CmsPackage\Http\Controllers\Access\PasswordLoginController;
use Made\CmsPackage\Http\Controllers\Access\SessionController;
use Made\CmsPackage\Http\Controllers\Access\StartPageController;
use Made\CmsPackage\Http\Controllers\Access\TwoFactorConfirmController;
use Made\CmsPackage\Http\Controllers\Access\TwoFactorLoginController;
use Made\CmsPackage\Http\Controllers\Access\TwoFactorSetupController;
use Made\CmsPackage\Http\Controllers\Access\UpcomingScreenController;
use Made\CmsPackage\Http\Middleware\EnsureEditor;
use Made\CmsPackage\Http\Middleware\EnsureScreenAccess;
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
            Route::post('login/code/verify', LoginCodeVerifyController::class)
                ->middleware(ThrottleRequests::using('made-cms-package.login'))
                ->name('login.code.verify');
            Route::post('login/password', PasswordLoginController::class)
                ->middleware(ThrottleRequests::using('made-cms-package.login'))
                ->name('login.password');
            Route::post('login/two-factor', TwoFactorLoginController::class)
                ->middleware(ThrottleRequests::using('made-cms-package.two-factor'))
                ->name('login.two-factor');
            Route::post('two-factor/setup', TwoFactorSetupController::class)
                ->middleware(ThrottleRequests::using('made-cms-package.two-factor'))
                ->name('two-factor.setup');
            Route::post('two-factor/confirm', TwoFactorConfirmController::class)
                ->middleware(ThrottleRequests::using('made-cms-package.two-factor'))
                ->name('two-factor.confirm');
            Route::post('logout', LogoutController::class)->name('logout');
        });

        Route::middleware(SetLauncherLocale::class)->group(function (): void {
            Route::get('/', LoginPageController::class)->name('login');
            Route::get('login/link/{token}', LoginLinkController::class)->name('login.link');
            Route::post('login/link/{token}', LoginLinkConfirmController::class)
                ->middleware(ThrottleRequests::using('made-cms-package.login'))
                ->name('login.link.confirm');

            Route::get('start', StartPageController::class)->middleware(EnsureEditor::class)->name('start');

            // Every screen opens at its own address; until a screen is built, it shows that it is coming.
            foreach (Screen::cases() as $screen) {
                Route::get($screen->value, UpcomingScreenController::class)
                    ->middleware(EnsureScreenAccess::using($screen))
                    ->defaults('screen', $screen->value)
                    ->name('screens.'.$screen->value);
            }

            // The edit shell: the page in the edit mode of the site, in a frame of the CMS.
            Route::get('pages/{page}/edit', EditPageController::class)
                ->middleware(EnsureScreenAccess::using(Screen::Pages))
                ->whereNumber('page')
                ->name('pages.edit');
        });
    });

// Static launcher assets load on every host page, so they run without middleware: no session, no cookies.
Route::prefix($prefix)
    ->name('made-cms-package.')
    ->group(function (): void {
        Route::get('launcher/{asset}', LauncherAssetController::class)->name('launcher.asset');
    });
