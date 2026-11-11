<?php

declare(strict_types=1);

use Made\CmsPackage\Access\Enums\Role;
use Made\CmsPackage\Access\Models\Editor;
use Made\CmsPackage\Content\Models\Media;
use Made\CmsPackage\Content\Models\MediaFolder;

beforeEach(function (): void {
    $this->actingAs(Editor::factory()->create(), 'made-cms');
});

it('lists the folders from a to z with the number of their files', function (): void {
    $teams = MediaFolder::factory()->create(['name' => 'Teams']);
    $nieuws = MediaFolder::factory()->create(['name' => 'Nieuws']);
    Media::factory()->in($teams)->count(2)->create();
    Media::factory()->in($teams)->document()->create();
    Media::factory()->create();

    $this->getJson('made-cms/media/folders')
        ->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertExactJson([
            'all' => 4,
            'folders' => [
                ['id' => $nieuws->id, 'name' => 'Nieuws', 'count' => 0],
                ['id' => $teams->id, 'name' => 'Teams', 'count' => 3],
            ],
        ]);

    $this->getJson('made-cms/media/folders?kind=image')
        ->assertJsonPath('all', 3)
        ->assertJsonPath('folders.1.count', 2);
});

it('refuses a kind it does not know', function (): void {
    $this->getJson('made-cms/media/folders?kind=foto')->assertUnprocessable()->assertJsonValidationErrors('kind');
});

it('opens for every editor who opens the pages or the media library', function (Role $role): void {
    $this->actingAs(Editor::factory()->withRoles($role)->create(), 'made-cms')
        ->getJson('made-cms/media/folders')
        ->assertOk();
})->with([Role::Owner, Role::SeoSpecialist, Role::ContentManager, Role::Admin]);

it('forbids it to an editor who opens neither', function (): void {
    $this->actingAs(Editor::factory()->withRoles()->create(), 'made-cms')
        ->getJson('made-cms/media/folders')
        ->assertForbidden();
});

it('answers 401 to a guest', function (): void {
    auth('made-cms')->logout();

    $this->getJson('made-cms/media/folders')->assertUnauthorized();
});
