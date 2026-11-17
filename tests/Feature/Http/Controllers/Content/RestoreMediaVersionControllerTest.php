<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;
use Made\CmsPackage\Access\Enums\Role;
use Made\CmsPackage\Access\Models\Editor;
use Made\CmsPackage\Content\Actions\ReplaceMedia;
use Made\CmsPackage\Content\Actions\UploadMedia;
use Made\CmsPackage\Content\Data\UploadedMedia;
use Made\CmsPackage\Content\Models\Media;

beforeEach(function (): void {
    Storage::fake('public');
    $this->actingAs(Editor::factory()->create(), 'made-cms');
});

function replacedTwice(): Media
{
    $media = UploadMedia::run(new UploadedMedia(photo(800, 600), 'een.jpg'));

    return ReplaceMedia::run($media, new UploadedMedia(photo(800, 600), 'twee.jpg'));
}

it('puts the version that waits back and answers with the file as it is stored now', function (): void {
    $media = replacedTwice();

    $this->postJson("made-cms/media/{$media->id}/restore")
        ->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertJsonPath('file.id', $media->id)
        ->assertJsonPath('file.name', 'een');

    expect(Media::query()->findOrFail($media->id)->version)->toBe(1);
});

it('refuses a file without a version that waits, keyed by version', function (): void {
    $media = Media::factory()->create();

    $this->postJson("made-cms/media/{$media->id}/restore")
        ->assertUnprocessable()
        ->assertJsonPath('errors.version.0', 'Er staat geen vorige versie klaar om terug te zetten.');
});

it('answers 404 for a file that is gone', function (): void {
    $this->postJson('made-cms/media/999/restore')->assertNotFound();
});

it('opens only for an editor who opens the media library', function (Role $role, int $status): void {
    $media = replacedTwice();

    $this->actingAs(Editor::factory()->withRoles($role)->create(), 'made-cms')
        ->postJson("made-cms/media/{$media->id}/restore")
        ->assertStatus($status);
})->with([
    'content manager' => [Role::ContentManager, 200],
    'owner' => [Role::Owner, 403],
    'seo specialist' => [Role::SeoSpecialist, 403],
]);

it('refuses an editor without roles and a guest', function (): void {
    $media = Media::factory()->replaced()->create();

    $this->actingAs(Editor::factory()->withRoles()->create(), 'made-cms')
        ->postJson("made-cms/media/{$media->id}/restore")
        ->assertForbidden();

    auth('made-cms')->logout();

    $this->postJson("made-cms/media/{$media->id}/restore")->assertUnauthorized();
});
