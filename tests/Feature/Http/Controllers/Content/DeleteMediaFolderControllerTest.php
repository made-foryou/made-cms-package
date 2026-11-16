<?php

declare(strict_types=1);

use Made\CmsPackage\Access\Enums\Role;
use Made\CmsPackage\Access\Models\Editor;
use Made\CmsPackage\Content\Models\Media;
use Made\CmsPackage\Content\Models\MediaFolder;

beforeEach(function (): void {
    $this->actingAs(Editor::factory()->create(), 'made-cms');
});

it('deletes an empty folder and answers without content', function (): void {
    $folder = MediaFolder::factory()->create(['name' => 'Teams']);

    $this->deleteJson('made-cms/media/folders/'.$folder->id)
        ->assertNoContent()
        ->assertHeader('Cache-Control', 'no-store, private');

    expect(MediaFolder::query()->count())->toBe(0);
});

it('refuses a folder that holds files, with their number', function (): void {
    $folder = MediaFolder::factory()->create(['name' => 'Teams']);
    Media::factory()->count(2)->in($folder)->create();

    $this->deleteJson('made-cms/media/folders/'.$folder->id)
        ->assertUnprocessable()
        ->assertJsonPath('errors.folder.0', 'Teams heeft nog 2 bestanden. Zet ze eerst in een andere map.');

    expect(MediaFolder::query()->count())->toBe(1);
});

it('answers 404 for a folder that is gone', function (): void {
    $this->deleteJson('made-cms/media/folders/999')->assertNotFound();
});

it('opens only for an editor who opens the media library', function (Role $role, int $status): void {
    $folder = MediaFolder::factory()->create();

    $this->actingAs(Editor::factory()->withRoles($role)->create(), 'made-cms')
        ->deleteJson('made-cms/media/folders/'.$folder->id)
        ->assertStatus($status);
})->with([
    'content manager' => [Role::ContentManager, 204],
    'owner' => [Role::Owner, 403],
    'seo specialist' => [Role::SeoSpecialist, 403],
]);

it('answers 401 to a guest', function (): void {
    auth('made-cms')->logout();

    $this->deleteJson('made-cms/media/folders/'.MediaFolder::factory()->create()->id)->assertUnauthorized();
});
