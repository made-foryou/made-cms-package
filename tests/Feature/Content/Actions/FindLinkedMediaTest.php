<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;
use Made\CmsPackage\Content\Actions\FindLinkedMedia;
use Made\CmsPackage\Content\Actions\ReplaceMedia;
use Made\CmsPackage\Content\Actions\UploadMedia;
use Made\CmsPackage\Content\Data\UploadedMedia;
use Made\CmsPackage\Content\Models\Media;

beforeEach(function (): void {
    Storage::fake('public');
});

it('finds the address of a file by the key of its folder, also after a new version', function (): void {
    $media = UploadMedia::run(new UploadedMedia(mediaFixture('wedstrijdschema.pdf'), 'schema.pdf'));

    expect(FindLinkedMedia::run($media->ulid))->toBe(Storage::disk('public')->url($media->path));

    $replaced = ReplaceMedia::run($media, new UploadedMedia(mediaFixture('wedstrijdschema.pdf'), 'schema-voorjaar.pdf'));

    expect(FindLinkedMedia::run($media->ulid))->toBe(Storage::disk('public')->url($replaced->path))
        ->and($replaced->path)->not->toBe($media->path);
});

it('finds nothing for a key of no file', function (string $key): void {
    Media::factory()->create(['ulid' => '01jc8x0000000000000000000a']);

    expect(FindLinkedMedia::run($key))->toBeNull();
})->with([
    'another file' => ['01jc8x0000000000000000000b'],
    'capitals' => ['01JC8X0000000000000000000A'],
    'a part' => ['01jc8x'],
    'empty' => [''],
]);
