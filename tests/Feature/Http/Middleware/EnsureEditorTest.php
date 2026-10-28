<?php

declare(strict_types=1);

use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Route;
use Made\CmsPackage\Access\Enums\Role;
use Made\CmsPackage\Access\Models\Editor;
use Made\CmsPackage\Http\Middleware\EnsureEditor;

beforeEach(function (): void {
    Route::middleware(['web', EnsureEditor::class])
        ->get('made-cms/test-editor-page', fn (): string => 'Binnen');
});

it('lets every logged-in editor through', function (Role $role): void {
    $this->actingAs(Editor::factory()->withRoles($role)->create(), 'made-cms')
        ->get('made-cms/test-editor-page')
        ->assertOk()
        ->assertSee('Binnen');
})->with(Role::cases());

it('sends a guest to the login page', function (): void {
    $this->get('made-cms/test-editor-page')
        ->assertRedirect(route('made-cms-package.login'));
});

it('answers a guest asking for json with unauthorized', function (): void {
    $this->getJson('made-cms/test-editor-page')
        ->assertUnauthorized();
});

it('ignores a login on another guard', function (): void {
    $this->actingAs(new User, 'web')
        ->get('made-cms/test-editor-page')
        ->assertRedirect(route('made-cms-package.login'));
});
