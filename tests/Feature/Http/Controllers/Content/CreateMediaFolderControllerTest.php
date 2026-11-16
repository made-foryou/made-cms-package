<?php

declare(strict_types=1);

use Made\CmsPackage\Access\Enums\Role;
use Made\CmsPackage\Access\Models\Editor;
use Made\CmsPackage\Content\Models\MediaFolder;

beforeEach(function (): void {
    $this->actingAs(Editor::factory()->create(), 'made-cms');
});

it('creates a folder and answers with it', function (): void {
    $response = $this->postJson('made-cms/media/folders', ['name' => '  Club   avond '])
        ->assertCreated()
        ->assertHeader('Cache-Control', 'no-store, private');

    $folder = MediaFolder::query()->sole();

    expect($response->json())->toBe(['folder' => ['id' => $folder->id, 'name' => 'Club avond']]);
});

it('shows the new folder in the folders of the picker and the library', function (): void {
    $this->postJson('made-cms/media/folders', ['name' => 'Teams'])->assertCreated();

    $this->getJson('made-cms/media/folders')
        ->assertOk()
        ->assertJsonPath('folders.0.name', 'Teams')
        ->assertJsonPath('folders.0.count', 0);
});

it('refuses a name it cannot take', function (array $body, string $message): void {
    MediaFolder::factory()->create(['name' => 'Teams']);

    $this->postJson('made-cms/media/folders', $body)
        ->assertUnprocessable()
        ->assertJsonPath('errors.name.0', $message);

    expect(MediaFolder::query()->count())->toBe(1);
})->with([
    'taken' => [['name' => 'teams'], 'Er is al een map Teams.'],
    'empty' => [['name' => '   '], 'Geef de map een naam.'],
    'missing' => [[], 'Geef de map een naam.'],
    'not a text' => [['name' => ['Teams']], 'Geef de map een naam.'],
    'too long' => [['name' => str_repeat('a', 61)], 'Een mapnaam mag hoogstens 60 tekens zijn.'],
]);

it('opens only for an editor who opens the media library', function (Role $role, int $status): void {
    $this->actingAs(Editor::factory()->withRoles($role)->create(), 'made-cms')
        ->postJson('made-cms/media/folders', ['name' => 'Teams'])
        ->assertStatus($status);
})->with([
    'content manager' => [Role::ContentManager, 201],
    'webmaster' => [Role::Webmaster, 201],
    'owner' => [Role::Owner, 403],
    'seo specialist' => [Role::SeoSpecialist, 403],
]);

it('refuses an editor without roles and answers 401 to a guest', function (): void {
    $this->actingAs(Editor::factory()->withRoles()->create(), 'made-cms')
        ->postJson('made-cms/media/folders', ['name' => 'Teams'])
        ->assertForbidden();

    auth('made-cms')->logout();
    $this->postJson('made-cms/media/folders', ['name' => 'Teams'])->assertUnauthorized();

    expect(MediaFolder::query()->count())->toBe(0);
});
