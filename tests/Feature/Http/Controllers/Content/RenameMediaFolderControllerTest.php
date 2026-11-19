<?php

declare(strict_types=1);

use Made\CmsPackage\Access\Enums\Role;
use Made\CmsPackage\Access\Models\Editor;
use Made\CmsPackage\Content\Models\MediaFolder;

beforeEach(function (): void {
    $this->actingAs(Editor::factory()->create(), 'made-cms');
});

it('renames a folder and answers with it', function (): void {
    $folder = MediaFolder::factory()->create(['name' => 'Teams']);

    $response = $this->putJson('made-cms/media/folders/'.$folder->id, ['name' => ' Teams  2026 '])
        ->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private');

    expect($response->json())->toBe(['folder' => ['id' => $folder->id, 'name' => 'Teams 2026']])
        ->and($folder->refresh()->name)->toBe('Teams 2026');
});

it('refuses a name it cannot take', function (array $body, string $message): void {
    MediaFolder::factory()->create(['name' => 'Nieuws']);
    $folder = MediaFolder::factory()->create(['name' => 'Teams']);

    $this->putJson('made-cms/media/folders/'.$folder->id, $body)
        ->assertUnprocessable()
        ->assertJsonPath('errors.name.0', $message);

    expect($folder->refresh()->name)->toBe('Teams');
})->with([
    'taken' => [['name' => 'NIEUWS'], 'Er is al een map Nieuws.'],
    'empty' => [['name' => ''], 'Geef de map een naam.'],
    'too long' => [['name' => str_repeat('a', 61)], 'Een mapnaam mag hoogstens 60 tekens zijn.'],
]);

it('answers 404 for a folder that is gone', function (): void {
    $this->putJson('made-cms/media/folders/'.missingId(), ['name' => 'Teams'])->assertNotFound();
});

it('opens only for an editor who opens the media library', function (Role $role, int $status): void {
    $folder = MediaFolder::factory()->create(['name' => 'Teams']);

    $this->actingAs(Editor::factory()->withRoles($role)->create(), 'made-cms')
        ->putJson('made-cms/media/folders/'.$folder->id, ['name' => 'Nieuws'])
        ->assertStatus($status);
})->with([
    'content manager' => [Role::ContentManager, 200],
    'owner' => [Role::Owner, 403],
    'seo specialist' => [Role::SeoSpecialist, 403],
]);

it('answers 401 to a guest', function (): void {
    auth('made-cms')->logout();

    $this->putJson('made-cms/media/folders/'.MediaFolder::factory()->create()->id, ['name' => 'Nieuws'])->assertUnauthorized();
});
