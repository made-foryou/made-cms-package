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
use Made\CmsPackage\Http\Controllers\Content\DeletePageController;
use Made\CmsPackage\Http\Controllers\Content\NewPageController;
use Made\CmsPackage\Http\Controllers\Content\PagesController;
use Made\CmsPackage\Http\Controllers\Content\RestorePageController;
use Made\CmsPackage\Http\Controllers\Content\ShowPageController;
use Made\CmsPackage\Http\Controllers\Content\ShowStripController;
use Made\CmsPackage\Http\Controllers\Content\StorePageController;
use Made\CmsPackage\Http\Controllers\Content\StripVisibilityController;
use Made\CmsPackage\Http\Controllers\Content\UpdatePageController;
use Made\CmsPackage\Http\Controllers\Content\UpdateStripController;
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

            // Every screen opens at its own address; until a screen is built, it shows that it is coming. A built screen
            // leaves the loop and keeps the name screens.<screen>, which the menu links to.
            foreach (Screen::cases() as $screen) {
                if ($screen === Screen::Pages) {
                    continue;
                }

                Route::get($screen->value, UpcomingScreenController::class)
                    ->middleware(EnsureScreenAccess::using($screen))
                    ->defaults('screen', $screen->value)
                    ->name('screens.'.$screen->value);
            }

            // The pages screen: the pages in the order of the menu, a page with its basics, a new page, deleting and
            // restoring. Plain forms; a refusal goes back to the form with the error.
            Route::middleware(EnsureScreenAccess::using(Screen::Pages))->group(function (): void {
                Route::get('pages', PagesController::class)->name('screens.pages');
                Route::get('pages/new', NewPageController::class)->name('pages.create');
                Route::post('pages', StorePageController::class)->name('pages.store');
                Route::get('pages/{page}', ShowPageController::class)->whereNumber('page')->name('pages.show');
                Route::put('pages/{page}', UpdatePageController::class)->whereNumber('page')->name('pages.update');
                Route::delete('pages/{page}', DeletePageController::class)->whereNumber('page')->name('pages.destroy');
                Route::post('pages/{page}/restore', RestorePageController::class)
                    ->whereNumber('page')
                    ->withTrashed()
                    ->name('pages.restore');

                // The edit shell: the page in the edit mode of the site, in a frame of the CMS.
                Route::get('pages/{page}/edit', EditPageController::class)->whereNumber('page')->name('pages.edit');

                // The side panel of the edit shell talks JSON, so the shell and the site in its frame stay loaded.
                Route::get('pages/{page}/strips/{strip}', ShowStripController::class)
                    ->whereNumber('page')
                    ->where('strip', '[A-Za-z0-9-]+')
                    ->name('pages.strips.show');
                Route::put('pages/{page}/strips/{strip}', UpdateStripController::class)
                    ->whereNumber('page')
                    ->where('strip', '[A-Za-z0-9-]+')
                    ->name('pages.strips.update');
                Route::put('pages/{page}/strips/{strip}/visibility', StripVisibilityController::class)
                    ->whereNumber('page')
                    ->where('strip', '[A-Za-z0-9-]+')
                    ->name('pages.strips.visibility');
            });
        });
    });

// Static launcher assets load on every host page, so they run without middleware: no session, no cookies.
Route::prefix($prefix)
    ->name('made-cms-package.')
    ->group(function (): void {
        Route::get('launcher/{asset}', LauncherAssetController::class)->name('launcher.asset');
    });
