<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;
use Made\CmsPackage\Content\Actions\RemoveMediaVersionFiles;
use Made\CmsPackage\Content\Data\MediaVersion;
use Made\CmsPackage\Content\Models\Media;

beforeEach(function (): void {
    Storage::fake('public');
});

const VERSION_ROOT = 'media/01jc8x0000000000000000000a';

/**
 * A version of the file at the root of its folder, the upload, as `previous` stores it.
 *
 * @return array<string, mixed>
 */
function pushedVersion(array $overrides = []): array
{
    return [
        'version' => 1,
        'name' => 'teamfoto',
        'path' => VERSION_ROOT.'/teamfoto.webp',
        'original_path' => VERSION_ROOT.'/original-v1.jpg',
        'thumb_path' => VERSION_ROOT.'/thumb-v1.webp',
        'mime' => 'image/webp',
        'size' => 100,
        ...$overrides,
    ];
}

/**
 * A row at version 3 in a folder of its own, with version 2 waiting in another, and every file on the disk.
 *
 * @param  list<string>  $more  files of the version that was pushed out
 */
function rowAfterThirdVersion(array $more): Media
{
    $media = Media::factory()->create([
        'ulid' => '01jc8x0000000000000000000a',
        'version' => 3,
        'path' => VERSION_ROOT.'/c3c3c3c3/derde.webp',
        'original_path' => VERSION_ROOT.'/c3c3c3c3/original-v3.jpg',
        'thumb_path' => VERSION_ROOT.'/c3c3c3c3/thumb-v3.webp',
        'previous' => pushedVersion([
            'version' => 2,
            'name' => 'tweede',
            'path' => VERSION_ROOT.'/b2b2b2b2/tweede.webp',
            'original_path' => VERSION_ROOT.'/b2b2b2b2/original-v2.jpg',
            'thumb_path' => VERSION_ROOT.'/b2b2b2b2/thumb-v2.webp',
        ]),
    ]);

    foreach ([...MediaVersion::held($media), ...$more] as $file) {
        Storage::disk('public')->put($file, 'bytes');
    }

    return $media;
}

it('removes the upload at the root of the folder file by file, with the folder of its crop', function (): void {
    $media = rowAfterThirdVersion([VERSION_ROOT.'/a1a1a1a1/teamfoto.webp', VERSION_ROOT.'/original-v1.jpg', VERSION_ROOT.'/thumb-v1.webp']);
    $held = MediaVersion::held($media);

    RemoveMediaVersionFiles::run($media->id, MediaVersion::fromArray(pushedVersion(['path' => VERSION_ROOT.'/a1a1a1a1/teamfoto.webp'])));

    expect(Storage::disk('public')->allFiles())->toEqualCanonicalizing($held)
        ->and(Storage::disk('public')->directoryExists(VERSION_ROOT.'/a1a1a1a1'))->toBeFalse();
});

it('removes an upload kept as it came in, and nothing else at the root', function (): void {
    $media = rowAfterThirdVersion([VERSION_ROOT.'/schema.pdf']);
    $held = MediaVersion::held($media);

    RemoveMediaVersionFiles::run($media->id, MediaVersion::fromArray(pushedVersion([
        'path' => VERSION_ROOT.'/schema.pdf',
        'original_path' => null,
        'thumb_path' => null,
        'mime' => 'application/pdf',
    ])));

    expect(Storage::disk('public')->allFiles())->toEqualCanonicalizing($held);
});

it('removes a version in a folder of its own with that folder', function (): void {
    $media = rowAfterThirdVersion([VERSION_ROOT.'/a1a1a1a1/eerste.webp', VERSION_ROOT.'/a1a1a1a1/original-v1.jpg', VERSION_ROOT.'/a1a1a1a1/d4d4d4d4/eerste.webp']);
    $held = MediaVersion::held($media);

    RemoveMediaVersionFiles::run($media->id, MediaVersion::fromArray(pushedVersion([
        'path' => VERSION_ROOT.'/a1a1a1a1/d4d4d4d4/eerste.webp',
        'original_path' => VERSION_ROOT.'/a1a1a1a1/original-v1.jpg',
        'thumb_path' => VERSION_ROOT.'/a1a1a1a1/thumb-v1.webp',
    ])));

    expect(Storage::disk('public')->allFiles())->toEqualCanonicalizing($held)
        ->and(Storage::disk('public')->directoryExists(VERSION_ROOT.'/a1a1a1a1'))->toBeFalse();
});

it('leaves a folder that holds a file the row holds now', function (): void {
    $media = rowAfterThirdVersion([]);

    // The version that waits is handed as if it were pushed out: its folder stays, it is held.
    RemoveMediaVersionFiles::run($media->id, MediaVersion::waiting($media));

    expect(Storage::disk('public')->allFiles())->toEqualCanonicalizing(MediaVersion::held($media));
});

it('removes nothing for a row that is gone', function (): void {
    $media = rowAfterThirdVersion([VERSION_ROOT.'/original-v1.jpg']);
    Media::query()->whereKey($media->id)->delete();

    RemoveMediaVersionFiles::run($media->id, MediaVersion::fromArray(pushedVersion()));

    expect(Storage::disk('public')->exists(VERSION_ROOT.'/original-v1.jpg'))->toBeTrue();
});

it('never removes a file or folder outside the folder of the file', function (): void {
    $media = rowAfterThirdVersion(['media/elders/a1a1a1a1/teamfoto.webp', 'media/elders/a1a1a1a1/original.jpg']);

    RemoveMediaVersionFiles::run($media->id, MediaVersion::fromArray(pushedVersion([
        'path' => 'media/elders/a1a1a1a1/teamfoto.webp',
        'original_path' => 'media/elders/a1a1a1a1/original.jpg',
        'thumb_path' => null,
    ])));

    expect(Storage::disk('public')->exists('media/elders/a1a1a1a1/teamfoto.webp'))->toBeTrue()
        ->and(Storage::disk('public')->exists('media/elders/a1a1a1a1/original.jpg'))->toBeTrue();
});
