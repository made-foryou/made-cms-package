<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;
use Made\CmsPackage\Access\Enums\Role;
use Made\CmsPackage\Access\Models\Editor;
use Made\CmsPackage\Content\Actions\UploadMedia;
use Made\CmsPackage\Content\Data\UploadedMedia;
use Made\CmsPackage\Content\Models\Media;

beforeEach(function (): void {
    Storage::fake('public');
    $this->actingAs(Editor::factory()->create(), 'made-cms');
});

it('saves the original of a photo as it was uploaded, never kept by a cache', function (): void {
    $path = photo(1200, 800, 'jpg', 'Teamfoto.jpg');
    $media = UploadMedia::run(new UploadedMedia($path, 'Teamfoto.jpg'));

    $response = $this->get("made-cms/media/{$media->id}/download")
        ->assertOk()
        ->assertHeader('Content-Type', 'image/jpeg')
        ->assertHeader('Content-Disposition', 'attachment; filename=teamfoto.jpg')
        ->assertHeader('Content-Length', (string) filesize($path))
        ->assertHeader('X-Content-Type-Options', 'nosniff');

    expect($response->headers->get('Cache-Control'))->toContain('no-store')
        ->and($response->streamedContent())->toBe(file_get_contents($path));
});

it('saves a file kept as it came in itself', function (): void {
    $media = UploadMedia::run(new UploadedMedia(mediaFixture('wedstrijdschema.pdf'), 'Schema najaar.pdf'));

    $response = $this->get("made-cms/media/{$media->id}/download")
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('Content-Disposition', 'attachment; filename=schema-najaar.pdf');

    expect($response->streamedContent())->toBe(file_get_contents(mediaFixture('wedstrijdschema.pdf')));
});

it('answers 404 for a file that is not on the disk or gone', function (): void {
    $media = Media::factory()->create();

    $this->get("made-cms/media/{$media->id}/download")->assertNotFound();
    $this->get('made-cms/media/'.missingId().'/download')->assertNotFound();
});

it('opens only for an editor who opens the media library', function (Role $role, int $status): void {
    $media = UploadMedia::run(new UploadedMedia(mediaFixture('wedstrijdschema.pdf'), 'schema.pdf'));

    $this->actingAs(Editor::factory()->withRoles($role)->create(), 'made-cms')
        ->getJson("made-cms/media/{$media->id}/download")
        ->assertStatus($status);
})->with([
    'content manager' => [Role::ContentManager, 200],
    'owner' => [Role::Owner, 403],
    'seo specialist' => [Role::SeoSpecialist, 403],
]);

it('refuses an editor without roles and a guest', function (): void {
    $media = Media::factory()->create();

    $this->actingAs(Editor::factory()->withRoles()->create(), 'made-cms')
        ->getJson("made-cms/media/{$media->id}/download")
        ->assertForbidden();

    auth('made-cms')->logout();

    $this->getJson("made-cms/media/{$media->id}/download")->assertUnauthorized();
});
