<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;
use Made\CmsPackage\Access\Enums\Role;
use Made\CmsPackage\Access\Models\Editor;
use Made\CmsPackage\Content\Actions\CropImage;
use Made\CmsPackage\Content\Actions\UploadMedia;
use Made\CmsPackage\Content\Data\CropArea;
use Made\CmsPackage\Content\Data\CropMedia;
use Made\CmsPackage\Content\Data\UploadedMedia;
use Made\CmsPackage\Content\Models\Media;

beforeEach(function (): void {
    Storage::fake('public');
    $this->actingAs(Editor::factory()->create(), 'made-cms');
});

it('shows the whole original of a cropped photo, scaled down, never stored by a cache', function (): void {
    config(['made-cms-package.media.image.max_width' => 600]);
    $media = UploadMedia::run(new UploadedMedia(quadrants(1200, 800), 'teamfoto.jpg'));
    CropImage::run($media, new CropMedia(new CropArea(0, 0, 300, 300)));

    $response = $this->get('made-cms/media/'.$media->id.'/original')
        ->assertOk()
        ->assertHeader('Content-Type', 'image/webp')
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertHeader('X-Content-Type-Options', 'nosniff');

    $contents = (string) $response->getContent();

    expect(getimagesizefromstring($contents))->toMatchArray([0 => 600, 1 => 400, 'mime' => 'image/webp'])
        ->and(quadrantsOf($contents))->toBe(['red', 'green', 'blue', 'yellow'])
        ->and($response->headers->get('Content-Length'))->toBe((string) strlen($contents));
});

it('says so when the original cannot be read', function (): void {
    $media = UploadMedia::run(new UploadedMedia(quadrants(300, 200), 'teamfoto.jpg'));
    Storage::disk('public')->delete((string) $media->original_path);

    $this->getJson('made-cms/media/'.$media->id.'/original')
        ->assertUnprocessable()
        ->assertJsonPath('errors.crop.0', 'Het origineel van deze foto is niet meer te lezen, dus hij kan niet opnieuw bijgesneden worden.');
});

it('answers 404 for a file without an original', function (): void {
    $this->get('made-cms/media/'.Media::factory()->document()->create()->id.'/original')->assertNotFound();
});

it('opens for every editor who opens the pages or the media library, and for nobody else', function (): void {
    $media = UploadMedia::run(new UploadedMedia(quadrants(300, 200), 'teamfoto.jpg'));

    foreach ([Role::Owner, Role::SeoSpecialist, Role::ContentManager] as $role) {
        $this->actingAs(Editor::factory()->withRoles($role)->create(), 'made-cms')
            ->get('made-cms/media/'.$media->id.'/original')
            ->assertOk();
    }

    $this->actingAs(Editor::factory()->withRoles()->create(), 'made-cms')
        ->get('made-cms/media/'.$media->id.'/original')
        ->assertForbidden();

    auth('made-cms')->logout();

    $this->getJson('made-cms/media/'.$media->id.'/original')->assertUnauthorized();
});
