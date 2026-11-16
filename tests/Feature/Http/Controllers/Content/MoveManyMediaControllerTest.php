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

it('puts the files into a folder and answers with them by id', function (): void {
    $folder = MediaFolder::factory()->create(['name' => 'Teams']);
    $first = Media::factory()->create();
    $second = Media::factory()->in(MediaFolder::factory()->create())->create();

    $response = $this->putJson('made-cms/media/move', ['files' => [$second->id, $first->id], 'folder' => $folder->id])
        ->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private');

    expect($response->json('files.*.id'))->toBe([$first->id, $second->id])
        ->and($response->json('files.*.folder'))->toBe(['Teams', 'Teams'])
        ->and($response->json('files.*.folder_id'))->toBe([$folder->id, $folder->id])
        ->and(Media::query()->inFolder($folder)->count())->toBe(2);
});

it('takes the files out of their folder and leaves out a file that is gone', function (): void {
    $media = Media::factory()->in(MediaFolder::factory()->create())->create();

    $this->putJson('made-cms/media/move', ['files' => [$media->id, 999], 'folder' => null])
        ->assertOk()
        ->assertJsonPath('files.*.id', [$media->id])
        ->assertJsonPath('files.0.folder', null);

    expect($media->refresh()->folder_id)->toBeNull();
});

it('refuses a folder that is gone or no folder, and moves nothing', function (array $body, string $message): void {
    $media = Media::factory()->create();

    $this->putJson('made-cms/media/move', ['files' => [$media->id], ...$body])
        ->assertUnprocessable()
        ->assertJsonPath('errors.folder.0', $message);

    expect($media->refresh()->folder_id)->toBeNull();
})->with([
    'gone' => [['folder' => 999], 'Deze map bestaat niet meer. Kies een andere map.'],
    'a text' => [['folder' => 'Teams'], 'Kies de map waar het bestand in komt.'],
    'missing' => [[], 'Kies de map waar het bestand in komt.'],
]);

it('refuses a list of files it cannot take', function (mixed $files): void {
    $this->putJson('made-cms/media/move', ['files' => $files, 'folder' => null])
        ->assertUnprocessable()
        ->assertJsonPath('errors', fn (array $errors): bool => collect($errors)->flatten()->unique()->values()->all() === ['Kies de bestanden die je wilt verplaatsen. Herlaad de pagina en probeer het opnieuw.']);
})->with([
    'none' => [[]],
    'not a list' => ['1,2'],
    'a text in it' => [[1, 'twee']],
    'twice the same' => [[1, 1]],
    'more than 200' => [range(1, 201)],
]);

it('opens only for an editor who opens the media library', function (Role $role, int $status): void {
    $media = Media::factory()->create();

    $this->actingAs(Editor::factory()->withRoles($role)->create(), 'made-cms')
        ->putJson('made-cms/media/move', ['files' => [$media->id], 'folder' => null])
        ->assertStatus($status);
})->with([
    'content manager' => [Role::ContentManager, 200],
    'owner' => [Role::Owner, 403],
    'seo specialist' => [Role::SeoSpecialist, 403],
]);

it('answers 401 to a guest', function (): void {
    auth('made-cms')->logout();

    $this->putJson('made-cms/media/move', ['files' => [Media::factory()->create()->id], 'folder' => null])->assertUnauthorized();
});
