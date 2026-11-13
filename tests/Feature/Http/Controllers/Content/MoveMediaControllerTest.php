<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;
use Made\CmsPackage\Access\Enums\Role;
use Made\CmsPackage\Access\Models\Editor;
use Made\CmsPackage\Content\Models\Media;
use Made\CmsPackage\Content\Models\MediaFolder;

beforeEach(function (): void {
    Storage::fake('public');
    $this->actingAs(Editor::factory()->create(), 'made-cms');
});

it('puts a file into a folder and answers with the file', function (): void {
    $folder = MediaFolder::factory()->create(['name' => 'Teams']);
    $media = Media::factory()->create();

    $this->putJson('made-cms/media/'.$media->id.'/move', ['folder' => $folder->id])
        ->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertJsonPath('file.folder', 'Teams');

    expect($media->refresh()->folder_id)->toBe($folder->id);
});

it('takes a file out of its folder', function (): void {
    $media = Media::factory()->in(MediaFolder::factory()->create())->create();

    $this->putJson('made-cms/media/'.$media->id.'/move', ['folder' => null])
        ->assertOk()
        ->assertJsonPath('file.folder', null);

    expect($media->refresh()->folder_id)->toBeNull();
});

it('refuses a folder that is gone or no folder', function (array $body, string $message): void {
    $media = Media::factory()->create();

    $this->putJson('made-cms/media/'.$media->id.'/move', $body)
        ->assertUnprocessable()
        ->assertJsonPath('errors.folder.0', $message);
})->with([
    'gone' => [['folder' => 999], 'Deze map bestaat niet meer. Herlaad de pagina en kies een andere map.'],
    'a text' => [['folder' => 'Teams'], 'Kies de map waar het bestand in komt.'],
    'missing' => [[], 'Kies de map waar het bestand in komt.'],
]);

it('opens only for an editor who opens the media library', function (Role $role, int $status): void {
    $media = Media::factory()->create();

    $this->actingAs(Editor::factory()->withRoles($role)->create(), 'made-cms')
        ->putJson('made-cms/media/'.$media->id.'/move', ['folder' => null])
        ->assertStatus($status);
})->with([
    'content manager' => [Role::ContentManager, 200],
    'owner' => [Role::Owner, 403],
    'seo specialist' => [Role::SeoSpecialist, 403],
]);

it('answers 401 to a guest', function (): void {
    auth('made-cms')->logout();

    $this->putJson('made-cms/media/'.Media::factory()->create()->id.'/move', ['folder' => null])->assertUnauthorized();
});
