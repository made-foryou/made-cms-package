<?php

declare(strict_types=1);

use Made\CmsPackage\CmsPackage;

it('resolves the singleton', function () {
    expect(app(CmsPackage::class))->toBeInstanceOf(CmsPackage::class);
});

it('returns the same instance from the container', function () {
    expect(app(CmsPackage::class))->toBe(app(CmsPackage::class));
});

it('merges the package config', function () {
    expect(config('made-cms-package.placeholder'))->toBe('default');
});

it('loads the package translations', function () {
    expect(trans('made-cms-package::messages.placeholder'))->toBe('CmsPackage placeholder translation.');
});

it('loads the package views', function () {
    expect(view()->exists('made-cms-package::placeholder'))->toBeTrue();
});

it('registers the artisan command', function () {
    $this->artisan('made-cms-package:placeholder')
        ->expectsOutputToContain('CmsPackage placeholder command executed.')
        ->assertSuccessful();
});
