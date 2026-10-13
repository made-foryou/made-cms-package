<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Made\CmsPackage\CmsPackageServiceProvider;

it('merges the package config', function (): void {
    expect(config('made-cms-package.routes.prefix'))->toBe('cms')
        ->and(config('made-cms-package.routes.middleware'))->toBe(['web'])
        ->and(config('made-cms-package.login_code.length'))->toBe(6)
        ->and(config('made-cms-package.login_code.ttl_minutes'))->toBe(10);
});

it('loads the Dutch translations by default', function (): void {
    expect(__('made-cms-package::pages.status.draft'))->toBe('Concept')
        ->and(__('made-cms-package::pages.status.published'))->toBe('Live');
});

it('loads the English translations', function (): void {
    app()->setLocale('en');

    expect(__('made-cms-package::pages.status.draft'))->toBe('Draft');
});

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
})->with(['cms_login_codes', 'cms_pages']);
