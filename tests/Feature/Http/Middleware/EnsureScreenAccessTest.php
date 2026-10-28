<?php

declare(strict_types=1);

use Illuminate\Foundation\Auth\User;
use Made\CmsPackage\Access\Enums\Role;
use Made\CmsPackage\Access\Enums\Screen;
use Made\CmsPackage\Access\Models\Editor;
use Made\CmsPackage\Http\Middleware\EnsureScreenAccess;

it('names the middleware with its screen', function (): void {
    expect(EnsureScreenAccess::using(Screen::Redirects))->toBe(EnsureScreenAccess::class.':redirects');
});

it('opens the screen for an editor whose role grants it', function (Role $role): void {
    $this->actingAs(Editor::factory()->withRoles($role)->create(), 'made-cms')
        ->get('made-cms/redirects')
        ->assertOk()
        ->assertSee('<h1 id="made-cms-titel">Redirects</h1>', false);
})->with([Role::SeoSpecialist, Role::Admin]);

it('forbids the screen to an editor whose roles do not grant it', function (): void {
    $this->actingAs(Editor::factory()->withRoles(Role::ContentManager, Role::Owner)->create(), 'made-cms')
        ->get('made-cms/redirects')
        ->assertForbidden();
});

it('sends a guest to the login page', function (): void {
    $this->get('made-cms/redirects')
        ->assertRedirect(route('made-cms-package.login'));
});

it('answers a guest asking for json with unauthorized', function (): void {
    $this->getJson('made-cms/redirects')
        ->assertUnauthorized();
});

it('ignores a login on another guard', function (): void {
    $this->actingAs(new User, 'web')
        ->get('made-cms/redirects')
        ->assertRedirect(route('made-cms-package.login'));
});
