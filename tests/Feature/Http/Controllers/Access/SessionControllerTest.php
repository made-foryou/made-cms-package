<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Made\CmsPackage\Access\Models\Editor;

it('tells a guest that nobody is logged in and hands out a csrf token', function (): void {
    $this->getJson('made-cms/session')
        ->assertOk()
        ->assertJsonPath('authenticated', false)
        ->assertJsonPath('view', 'hidden')
        ->assertJsonPath('editor', null)
        ->assertJson(['csrf_token' => session()->token()]);
});

it('describes the editor who is logged in to the cms', function (): void {
    $editor = Editor::factory()->create(['name' => 'Menno Tempelaar']);

    $this->actingAs($editor, 'made-cms')
        ->getJson('made-cms/session')
        ->assertOk()
        ->assertJsonPath('authenticated', true)
        ->assertJsonPath('view', 'edit_bar')
        ->assertJsonPath('editor', ['first_name' => 'Menno', 'initial' => 'M']);
});

it('ignores a user who is only logged in to the host site', function (): void {
    $user = new GenericUser(['id' => 1, 'name' => 'Site user']);

    $this->actingAs($user, 'web')
        ->getJson('made-cms/session')
        ->assertJsonPath('authenticated', false);
});

it('is never stored by a cache', function (): void {
    expect($this->getJson('made-cms/session')->headers->getCacheControlDirective('no-store'))->toBeTrue();
});
