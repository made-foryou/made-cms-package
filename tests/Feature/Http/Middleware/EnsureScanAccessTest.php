<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Made\CmsPackage\Access\Enums\Role;
use Made\CmsPackage\Access\Models\Editor;
use Made\CmsPackage\Http\Middleware\EnsureScanAccess;

beforeEach(function (): void {
    Route::middleware(['web', EnsureScanAccess::class])->get('scan-probe', static fn (): string => 'scan');
});

it('lets an admin through on the machine of a developer', function (): void {
    $this->app->detectEnvironment(static fn (): string => 'local');

    $this->actingAs(Editor::factory()->withRoles(Role::Admin)->create(), 'made-cms')
        ->getJson('scan-probe')
        ->assertOk()
        ->assertSee('scan');
});

it('forbids the scan outside the local environment, also to an admin', function (): void {
    $this->actingAs(Editor::factory()->withRoles(Role::Admin)->create(), 'made-cms')
        ->getJson('scan-probe')
        ->assertForbidden();
});

it('forbids the scan to an editor without the admin role', function (): void {
    $this->app->detectEnvironment(static fn (): string => 'local');

    $this->actingAs(Editor::factory()->withRoles(Role::Webmaster, Role::Owner)->create(), 'made-cms')
        ->getJson('scan-probe')
        ->assertForbidden();
});

it('answers a guest asking for json with unauthorized and sends any other guest to the login page', function (): void {
    $this->app->detectEnvironment(static fn (): string => 'local');

    $this->getJson('scan-probe')->assertUnauthorized();
    $this->get('scan-probe')->assertRedirect(route('made-cms-package.login'));
});
