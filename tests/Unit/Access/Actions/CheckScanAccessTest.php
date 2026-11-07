<?php

declare(strict_types=1);

use Made\CmsPackage\Access\Actions\CheckScanAccess;
use Made\CmsPackage\Access\Data\ScanVisit;
use Made\CmsPackage\Access\Enums\Role;
use Made\CmsPackage\Access\Models\Editor;

it('opens the scan for an admin on the machine of a developer', function (): void {
    $this->app->detectEnvironment(static fn (): string => 'local');

    expect(CheckScanAccess::run(new ScanVisit(Editor::factory()->withRoles(Role::Webmaster, Role::Admin)->make())))->toBeTrue();
});

it('keeps the scan closed outside the local environment, also for an admin', function (string $environment): void {
    $this->app->detectEnvironment(static fn (): string => $environment);

    try {
        expect(CheckScanAccess::run(new ScanVisit(Editor::factory()->withRoles(Role::Admin)->make())))->toBeFalse();
    } finally {
        // Tearing down in production would ask to confirm the migrations.
        $this->app->detectEnvironment(static fn (): string => 'testing');
    }
})->with(['production', 'staging', 'testing']);

it('keeps the scan closed for an editor without the admin role', function (Role $role): void {
    $this->app->detectEnvironment(static fn (): string => 'local');

    expect(CheckScanAccess::run(new ScanVisit(Editor::factory()->withRoles($role)->make())))->toBeFalse();
})->with(array_values(array_filter(Role::cases(), static fn (Role $role): bool => $role !== Role::Admin)));

it('keeps the scan closed without an editor', function (): void {
    $this->app->detectEnvironment(static fn (): string => 'local');

    expect(CheckScanAccess::run(new ScanVisit(null)))->toBeFalse();
});
