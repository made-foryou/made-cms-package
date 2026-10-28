<?php

declare(strict_types=1);

use Made\CmsPackage\Access\Enums\Role;
use Made\CmsPackage\Access\Enums\Screen;
use Made\CmsPackage\Access\Models\Editor;

beforeEach(function (): void {
    config(['app.url' => 'http://localhost', 'app.name' => 'TTV Merwestad']);
});

it('gives every screen an address of its own under the cms prefix', function (Screen $screen): void {
    expect(route('made-cms-package.screens.'.$screen->value, [], false))->toBe('/made-cms/'.$screen->value);
})->with(Screen::cases());

it('opens a screen exactly for the roles that grant it', function (Role $role, Screen $screen): void {
    $response = $this->actingAs(Editor::factory()->withRoles($role)->create(), 'made-cms')
        ->get('made-cms/'.$screen->value);

    if (in_array($screen, $role->screens(), true)) {
        $response->assertOk()->assertSee('<h1 id="made-cms-titel">'.e($screen->label()).'</h1>', false);
    } else {
        $response->assertForbidden();
    }
})->with(Role::cases())->with(Screen::cases());

it('sends a guest to the login page', function (): void {
    $this->get('made-cms/news')
        ->assertRedirect(route('made-cms-package.login'));
});

it('tells the editor that the screen is still coming', function (): void {
    $this->actingAs(Editor::factory()->withRoles(Role::SeoSpecialist)->create(), 'made-cms')
        ->get('made-cms/redirects')
        ->assertSee('<title>Redirects · TTV Merwestad</title>', false)
        ->assertSee('Vindbaarheid')
        ->assertSee('Dit onderdeel van het CMS wordt nog gebouwd.')
        ->assertSee('href="/made-cms/start"', false);
});

it('marks the screen as the current menu item', function (): void {
    $response = $this->actingAs(Editor::factory()->withRoles(Role::SeoSpecialist)->create(), 'made-cms')
        ->get('made-cms/redirects');

    $response->assertSee('href="/made-cms/redirects" aria-current="page"', false);

    expect(substr_count((string) $response->getContent(), 'aria-current="page"'))->toBe(1);
});

it('is never stored by a cache, framed or indexed', function (): void {
    $response = $this->actingAs(Editor::factory()->withRoles(Role::Admin)->create(), 'made-cms')
        ->get('made-cms/log')
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow');

    expect($response->headers->getCacheControlDirective('no-store'))->toBeTrue();
});
