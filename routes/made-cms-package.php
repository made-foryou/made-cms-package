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
use Made\CmsPackage\Http\Controllers\Content\AddStripController;
use Made\CmsPackage\Http\Controllers\Content\CreateMediaFolderController;
use Made\CmsPackage\Http\Controllers\Content\CropMediaController;
use Made\CmsPackage\Http\Controllers\Content\DeleteMediaController;
use Made\CmsPackage\Http\Controllers\Content\DeleteMediaFolderController;
use Made\CmsPackage\Http\Controllers\Content\DeletePageController;
use Made\CmsPackage\Http\Controllers\Content\DiscardDraftController;
use Made\CmsPackage\Http\Controllers\Content\DownloadMediaController;
use Made\CmsPackage\Http\Controllers\Content\GenerateStripTypesController;
use Made\CmsPackage\Http\Controllers\Content\ListMediaController;
use Made\CmsPackage\Http\Controllers\Content\ListMediaFoldersController;
use Made\CmsPackage\Http\Controllers\Content\MediaLibraryController;
use Made\CmsPackage\Http\Controllers\Content\MediaLinkController;
use Made\CmsPackage\Http\Controllers\Content\MoveManyMediaController;
use Made\CmsPackage\Http\Controllers\Content\MoveMediaController;
use Made\CmsPackage\Http\Controllers\Content\MoveStripController;
use Made\CmsPackage\Http\Controllers\Content\NewPageController;
use Made\CmsPackage\Http\Controllers\Content\PagesController;
use Made\CmsPackage\Http\Controllers\Content\PublishPageController;
use Made\CmsPackage\Http\Controllers\Content\RememberMediaFolderController;
use Made\CmsPackage\Http\Controllers\Content\RemoveStripController;
use Made\CmsPackage\Http\Controllers\Content\RenameMediaController;
use Made\CmsPackage\Http\Controllers\Content\RenameMediaFolderController;
use Made\CmsPackage\Http\Controllers\Content\ReplaceMediaController;
use Made\CmsPackage\Http\Controllers\Content\RestoreMediaVersionController;
use Made\CmsPackage\Http\Controllers\Content\RestorePageController;
use Made\CmsPackage\Http\Controllers\Content\RestoreStripController;
use Made\CmsPackage\Http\Controllers\Content\ShowDraftController;
use Made\CmsPackage\Http\Controllers\Content\ShowMediaController;
use Made\CmsPackage\Http\Controllers\Content\ShowMediaCropController;
use Made\CmsPackage\Http\Controllers\Content\ShowMediaOriginalController;
use Made\CmsPackage\Http\Controllers\Content\ShowPageController;
use Made\CmsPackage\Http\Controllers\Content\ShowStripController;
use Made\CmsPackage\Http\Controllers\Content\ShowStripsController;
use Made\CmsPackage\Http\Controllers\Content\StorePageController;
use Made\CmsPackage\Http\Controllers\Content\StripVisibilityController;
use Made\CmsPackage\Http\Controllers\Content\UpdateAltTextController;
use Made\CmsPackage\Http\Controllers\Content\UpdatePageController;
use Made\CmsPackage\Http\Controllers\Content\UpdateStripController;
use Made\CmsPackage\Http\Controllers\Content\UploadMediaController;
use Made\CmsPackage\Http\Middleware\EnsureEditor;
use Made\CmsPackage\Http\Middleware\EnsureScanAccess;
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
                ->middleware(ThrottleRequests::using('made-cms-package.password'))
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
                if (in_array($screen, [Screen::Pages, Screen::Media], true)) {
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

                // The opbouw of the edit shell: adding, moving, removing a strip and putting a removed one back. Every
                // answer holds the new list of strips, so the shell keeps its places in step with the draft.
                Route::get('pages/{page}/strips', ShowStripsController::class)->whereNumber('page')->name('pages.strips.index');
                Route::post('pages/{page}/strips', AddStripController::class)->whereNumber('page')->name('pages.strips.store');
                Route::put('pages/{page}/strips/{strip}/position', MoveStripController::class)
                    ->whereNumber('page')
                    ->where('strip', '[A-Za-z0-9-]+')
                    ->name('pages.strips.position');
                Route::delete('pages/{page}/strips/{strip}', RemoveStripController::class)
                    ->whereNumber('page')
                    ->where('strip', '[A-Za-z0-9-]+')
                    ->name('pages.strips.destroy');
                Route::post('pages/{page}/strips/{strip}/restore', RestoreStripController::class)
                    ->whereNumber('page')
                    ->where('strip', '[A-Za-z0-9-]+')
                    ->name('pages.strips.restore');

                // The bar of the edit shell talks JSON too: the state of the draft, publishing it and throwing it away.
                Route::get('pages/{page}/draft', ShowDraftController::class)->whereNumber('page')->name('pages.draft.show');
                Route::delete('pages/{page}/draft', DiscardDraftController::class)->whereNumber('page')->name('pages.draft.destroy');
                Route::post('pages/{page}/publish', PublishPageController::class)->whereNumber('page')->name('pages.publish');
            });

            // The upload field, the picker and the crop step talk JSON. They stand in the edit shell (the pages) and in
            // the media library, so they open for an editor who opens one of the two. The list has an address of its own:
            // made-cms/media is the screen of the library.
            Route::middleware(EnsureScreenAccess::using(Screen::Pages, Screen::Media))->group(function (): void {
                Route::get('media/files', ListMediaController::class)->name('media.files');
                Route::get('media/folders', ListMediaFoldersController::class)->name('media.folders');
                Route::put('media/chosen-folder', RememberMediaFolderController::class)->name('media.chosen-folder.update');
                Route::post('media', UploadMediaController::class)->name('media.store');
                Route::put('media/{media}/alt', UpdateAltTextController::class)->whereNumber('media')->name('media.alt.update');
                Route::get('media/{media}/crop', ShowMediaCropController::class)->whereNumber('media')->name('media.crop.show');
                Route::get('media/{media}/original', ShowMediaOriginalController::class)->whereNumber('media')->name('media.original');
                Route::post('media/{media}/crop', CropMediaController::class)->whereNumber('media')->name('media.crop.store');
                Route::put('media/{media}/name', RenameMediaController::class)->whereNumber('media')->name('media.name.update');
            });

            // The media library and managing it: the screen, a file with the pages it is used on for its side column,
            // putting files into another folder (one from the crop step, the chosen ones from the library), a new
            // version, putting the former one back, saving a file, deleting files and its folders.
            Route::middleware(EnsureScreenAccess::using(Screen::Media))->group(function (): void {
                Route::get('media', MediaLibraryController::class)->name('screens.media');
                Route::get('media/{media}', ShowMediaController::class)->whereNumber('media')->name('media.show');
                Route::put('media/{media}/move', MoveMediaController::class)->whereNumber('media')->name('media.move');
                Route::put('media/move', MoveManyMediaController::class)->name('media.move.many');
                Route::post('media/{media}/replace', ReplaceMediaController::class)->whereNumber('media')->name('media.replace');
                Route::post('media/{media}/restore', RestoreMediaVersionController::class)->whereNumber('media')->name('media.restore');
                Route::get('media/{media}/download', DownloadMediaController::class)->whereNumber('media')->name('media.download');
                Route::post('media/delete', DeleteMediaController::class)->name('media.destroy');
                Route::post('media/folders', CreateMediaFolderController::class)->name('media.folders.store');
                Route::put('media/folders/{folder}', RenameMediaFolderController::class)->whereNumber('folder')->name('media.folders.update');
                Route::delete('media/folders/{folder}', DeleteMediaFolderController::class)->whereNumber('folder')->name('media.folders.destroy');
            });

            // The scan of the site writes code into it: only for an admin at APP_ENV=local, whatever its panel shows.
            Route::middleware(EnsureScanAccess::class)->group(function (): void {
                Route::post('scan/strip-types', GenerateStripTypesController::class)->name('scan.strip-types.store');
            });
        });
    });

// Static launcher assets load on every host page, so they run without middleware: no session, no cookies. The lasting
// link of a file of the media library is for anyone too: it only sends the visitor to the address the file has now.
Route::prefix($prefix)
    ->name('made-cms-package.')
    ->group(function (): void {
        Route::get('launcher/{asset}', LauncherAssetController::class)->name('launcher.asset');
        Route::get('file/{ulid}/{name?}', MediaLinkController::class)->where('ulid', '[0-9a-z]{26}')->name('media.link');
    });
