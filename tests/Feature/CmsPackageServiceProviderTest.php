<?php

declare(strict_types=1);

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Routing\Router;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\Compilers\BladeCompiler;
use Made\CmsPackage\Access\Models\Editor;
use Made\CmsPackage\CmsPackageServiceProvider;
use Made\CmsPackage\Content\Data\EditMode;
use Made\CmsPackage\Content\Models\Page;
use Made\CmsPackage\Content\Strips\StripTypes;
use Made\CmsPackage\Http\Middleware\InjectLauncher;

it('merges the package config', function (): void {
    expect(config('made-cms-package.routes.prefix'))->toBe('made-cms')
        ->and(config('made-cms-package.routes.middleware'))->toBe(['web'])
        ->and(config('made-cms-package.auth.guard'))->toBe('made-cms')
        ->and(config('made-cms-package.auth.remember_days'))->toBe(30)
        ->and(config('made-cms-package.login_code.length'))->toBe(6)
        ->and(config('made-cms-package.login_code.ttl_minutes'))->toBe(10)
        ->and(config('made-cms-package.login_code.max_attempts'))->toBe(5)
        ->and(config('made-cms-package.mail.from_name'))->toBe('Made CMS')
        ->and(config('made-cms-package.mail.help_url'))->toBeNull()
        ->and(config('made-cms-package.two_factor.window'))->toBe(1)
        ->and(config('made-cms-package.two_factor.recovery_codes'))->toBe(8)
        ->and(config('made-cms-package.launcher.enabled'))->toBeTrue()
        ->and(config('made-cms-package.launcher.allowed_ips'))->toBe([])
        ->and(config('made-cms-package.launcher.locale'))->toBe('nl')
        ->and(config('made-cms-package.launcher.except'))->toBe(['nova*', 'telescope*', 'horizon*', 'pulse*'])
        ->and(config('made-cms-package.strips.path'))->toBe(app_path('Cms/Strips'))
        ->and(config('made-cms-package.strips.namespace'))->toBe('App\\Cms\\Strips')
        ->and(config('made-cms-package.media.disk'))->toBe('public')
        ->and(config('made-cms-package.media.max_upload_kb'))->toBe(25600)
        ->and(config('made-cms-package.media.allowed_mimes'))->toBe([
            'image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/svg+xml', 'application/pdf', 'video/mp4',
        ])
        ->and(config('made-cms-package.media.image.driver'))->toBe('gd')
        ->and(config('made-cms-package.media.image.max_width'))->toBe(1920)
        ->and(config('made-cms-package.media.image.format'))->toBe('webp')
        ->and(config('made-cms-package.media.image.quality'))->toBe(82);
});

it('builds the strip type catalogue once from the configured folder', function (): void {
    config([
        'made-cms-package.strips.path' => __DIR__.'/../Fixtures/Strips/Valid',
        'made-cms-package.strips.namespace' => 'Made\\CmsPackage\\Tests\\Fixtures\\Strips\\Valid',
    ]);

    $catalogue = app(StripTypes::class);

    expect($catalogue->has('hero'))->toBeTrue()
        ->and($catalogue->has('cards'))->toBeTrue()
        ->and(app(StripTypes::class))->toBe($catalogue);
});

it('has an empty catalogue while the site has no strip types', function (): void {
    expect(app(StripTypes::class)->all())->toBe([]);
});

it('binds the edit mode off', function (): void {
    expect(app(EditMode::class)->active)->toBeFalse();
});

it('forgets the edit mode of a request with the scoped instances', function (): void {
    app()->instance(EditMode::class, EditMode::on());

    app()->forgetScopedInstances();

    expect(app(EditMode::class)->active)->toBeFalse();
});

it('registers its own session guard and editor provider next to the host guards', function (): void {
    expect(config('auth.guards.made-cms'))->toBe(['driver' => 'session', 'provider' => 'made-cms'])
        ->and(config('auth.providers.made-cms'))->toBe(['driver' => 'eloquent', 'model' => Editor::class])
        ->and(config('auth.defaults.guard'))->toBe('web')
        ->and(auth()->guard('made-cms')->getProvider()->createModel())->toBeInstanceOf(Editor::class);
});

it('keeps a guard and provider the host defined itself', function (): void {
    config([
        'auth.guards.made-cms' => ['driver' => 'session', 'provider' => 'host-editors'],
        'auth.providers.made-cms' => ['driver' => 'database', 'table' => 'host_editors'],
    ]);

    (new CmsPackageServiceProvider(app()))->register();

    expect(config('auth.guards.made-cms.provider'))->toBe('host-editors')
        ->and(config('auth.providers.made-cms.driver'))->toBe('database');
});

it('registers the editor command', function (): void {
    expect(Artisan::all())->toHaveKey('made-cms:editor');
});

it('registers the command that builds the usage of media again', function (): void {
    expect(Artisan::all())->toHaveKey('made-cms:media-usage');
});

it('registers the command that makes the missing thumbnails', function (): void {
    expect(Artisan::all())->toHaveKey('made-cms:media-thumbnails');
});

it('registers the prune command and runs it every day', function (): void {
    $events = collect(app(Schedule::class)->events())
        ->filter(fn (Event $event): bool => str_contains((string) $event->command, 'made-cms:prune'));

    expect(Artisan::all())->toHaveKey('made-cms:prune')
        ->and($events)->toHaveCount(1)
        ->and($events->first()?->expression)->toBe('0 0 * * *');
});

it('keeps deleted pages for thirty days', function (): void {
    expect(config('made-cms-package.pages.keep_deleted_days'))->toBe(30);
});

it('appends the launcher middleware to the web middleware group', function (): void {
    /** @var Illuminate\Foundation\Http\Kernel $kernel */
    $kernel = app(Kernel::class);

    expect($kernel->getMiddlewareGroups()['web'])->toContain(InjectLauncher::class)
        ->and(app('router')->getMiddlewareGroups()['web'])->toContain(InjectLauncher::class);
});

it('registers the launcher asset route without middleware', function (): void {
    $route = Route::getRoutes()->getByName('made-cms-package.launcher.asset');

    expect($route)->not->toBeNull()
        ->and($route?->uri())->toBe('made-cms/launcher/{asset}')
        ->and($route?->gatherMiddleware())->toBe([]);
});

it('loads the Dutch translations by default', function (): void {
    expect(__('made-cms-package::pages.status.draft'))->toBe('Concept')
        ->and(__('made-cms-package::pages.status.published'))->toBe('Live')
        ->and(__('made-cms-package::launcher.title'))->toBe('Beheer deze site');
});

it('loads the English translations', function (): void {
    app()->setLocale('en');

    expect(__('made-cms-package::pages.status.draft'))->toBe('Draft')
        ->and(__('made-cms-package::launcher.title'))->toBe('Manage this site');
});

it('ships every translation in Dutch and English', function (string $file): void {
    $dutch = require __DIR__."/../../lang/nl/{$file}.php";
    $english = require __DIR__."/../../lang/en/{$file}.php";

    expect(array_keys(Arr::dot($english)))->toBe(array_keys(Arr::dot($dutch)));
})->with(['pages', 'launcher', 'console', 'mail', 'strips', 'scan', 'media']);

it('registers the view namespace', function (): void {
    expect(app('view')->getFinder()->getHints())->toHaveKey('made-cms-package');
});

it('registers the made-cms blade component namespace', function (): void {
    expect(app(BladeCompiler::class)->getClassComponentNamespaces())
        ->toHaveKey('made-cms', 'Made\\CmsPackage\\Http\\Components');
});

it('registers the publish tags promised in the README', function (string $tag): void {
    expect(ServiceProvider::pathsToPublish(CmsPackageServiceProvider::class, $tag))->not->toBeEmpty();
})->with([
    'made-cms-package',
    'made-cms-package-config',
    'made-cms-package-migrations',
    'made-cms-package-views',
    'made-cms-package-lang',
    'made-cms-package-assets',
]);

it('ships the migrations for every context', function (string $table): void {
    expect(Schema::hasTable($table))->toBeTrue();
})->with(['cms_editors', 'cms_login_codes', 'cms_pages', 'cms_routes', 'cms_media_folders', 'cms_media', 'cms_media_usages']);

it('maps the page model to a morph alias without forcing a map on the host', function (): void {
    expect(Relation::getMorphedModel('cms_page'))->toBe(Page::class)
        ->and((new Page)->getMorphClass())->toBe('cms_page')
        ->and(Relation::requiresMorphMap())->toBeFalse();
});

it('registers the madeCms route macro', function (): void {
    expect(Route::hasMacro('madeCms'))->toBeTrue();
});

it('has the route macro ready before any provider boots, so the routes of a site can use it', function (): void {
    Router::flushMacros();

    (new CmsPackageServiceProvider(app()))->register();

    expect(Router::hasMacro('madeCms'))->toBeTrue();
});
