<?php

declare(strict_types=1);

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Made\CmsPackage\Access\Models\Editor;
use Made\CmsPackage\CmsPackageServiceProvider;
use Made\CmsPackage\Http\Middleware\InjectLauncher;

it('merges the package config', function (): void {
    expect(config('made-cms-package.routes.prefix'))->toBe('cms')
        ->and(config('made-cms-package.routes.middleware'))->toBe(['web'])
        ->and(config('made-cms-package.auth.guard'))->toBe('made-cms')
        ->and(config('made-cms-package.auth.remember_days'))->toBe(30)
        ->and(config('made-cms-package.login_code.length'))->toBe(6)
        ->and(config('made-cms-package.login_code.ttl_minutes'))->toBe(10)
        ->and(config('made-cms-package.login_code.max_attempts'))->toBe(5)
        ->and(config('made-cms-package.launcher.enabled'))->toBeTrue()
        ->and(config('made-cms-package.launcher.allowed_ips'))->toBe([])
        ->and(config('made-cms-package.launcher.locale'))->toBe('nl')
        ->and(config('made-cms-package.launcher.except'))->toBe(['nova*', 'telescope*', 'horizon*', 'pulse*']);
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

it('appends the launcher middleware to the web middleware group', function (): void {
    /** @var Illuminate\Foundation\Http\Kernel $kernel */
    $kernel = app(Kernel::class);

    expect($kernel->getMiddlewareGroups()['web'])->toContain(InjectLauncher::class)
        ->and(app('router')->getMiddlewareGroups()['web'])->toContain(InjectLauncher::class);
});

it('registers the launcher asset route without middleware', function (): void {
    $route = Route::getRoutes()->getByName('made-cms-package.launcher.asset');

    expect($route)->not->toBeNull()
        ->and($route?->uri())->toBe('cms/launcher/{asset}')
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
})->with(['pages', 'launcher', 'console', 'mail']);

it('registers the view namespace', function (): void {
    expect(app('view')->getFinder()->getHints())->toHaveKey('made-cms-package');
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
})->with(['cms_editors', 'cms_login_codes', 'cms_pages']);
