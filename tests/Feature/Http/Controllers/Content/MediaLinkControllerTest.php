<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;
use Made\CmsPackage\Content\Actions\DeleteMedia;
use Made\CmsPackage\Content\Actions\ReplaceMedia;
use Made\CmsPackage\Content\Actions\UploadMedia;
use Made\CmsPackage\Content\Data\UploadedMedia;

beforeEach(function (): void {
    Storage::fake('public');
});

it('sends anyone to the address a file has now, also after a new version, without a session', function (): void {
    $media = UploadMedia::run(new UploadedMedia(mediaFixture('wedstrijdschema.pdf'), 'tarieven.pdf'));

    $this->get("made-cms/file/{$media->ulid}/tarieven")
        ->assertFound()
        ->assertRedirect(Storage::disk('public')->url($media->path))
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertCookieMissing(config('session.cookie'))
        ->assertHeaderMissing('Set-Cookie');

    $replaced = ReplaceMedia::run($media, new UploadedMedia(mediaFixture('wedstrijdschema.pdf'), 'tarieven-2027.pdf'));

    $this->get("made-cms/file/{$media->ulid}/tarieven")->assertRedirect(Storage::disk('public')->url($replaced->path));
    $this->get("made-cms/file/{$media->ulid}")->assertRedirect(Storage::disk('public')->url($replaced->path));
});

it('answers 404 once the file left the library, or for a key of no file', function (): void {
    $media = UploadMedia::run(new UploadedMedia(mediaFixture('wedstrijdschema.pdf'), 'tarieven.pdf'));
    DeleteMedia::run([$media->id]);

    $this->get("made-cms/file/{$media->ulid}/tarieven")->assertNotFound();
    $this->get('made-cms/file/01jc8x0000000000000000000a')->assertNotFound();
    $this->get('made-cms/file/niet-een-sleutel')->assertNotFound();
});
