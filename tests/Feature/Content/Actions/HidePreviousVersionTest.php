<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\UnableToSetVisibility;
use Made\CmsPackage\Content\Actions\HidePreviousVersion;
use Made\CmsPackage\Content\Data\MediaVersion;
use Made\CmsPackage\Content\Exceptions\MediaNotStored;
use Made\CmsPackage\Content\Models\Media;

/**
 * A replaced photo with the files of both versions public on the disk.
 */
function replacedOnDisk(): Media
{
    $media = Media::factory()->replaced()->create();

    foreach (MediaVersion::held($media) as $file) {
        Storage::disk('public')->put($file, 'bytes', 'public');
    }

    return $media;
}

it('makes every file of the version that waits private, and none of the version the site shows', function (): void {
    $disk = recordMediaDisk();
    $media = replacedOnDisk();
    $waiting = MediaVersion::waiting($media);

    HidePreviousVersion::run($media->id);

    foreach ((array) $waiting?->files() as $file) {
        expect($disk->written($file)['visibility'] ?? null)->toBe('private');
    }

    foreach (MediaVersion::current($media)->files() as $file) {
        expect($disk->written($file)['visibility'] ?? null)->toBe('public');
    }
});

it('makes the files private on a local disk', function (): void {
    Storage::fake('public');
    $media = replacedOnDisk();

    HidePreviousVersion::run($media->id);

    expect(Storage::disk('public')->getVisibility((string) MediaVersion::waiting($media)?->path))->toBe('private')
        ->and(Storage::disk('public')->getVisibility($media->path))->toBe('public');
})->skipOnWindows();

it('leaves a file both versions hold', function (): void {
    $disk = recordMediaDisk();
    $media = replacedOnDisk();
    $previous = (array) $media->previous;
    $media->update(['previous' => [...$previous, 'original_path' => $media->original_path]]);

    HidePreviousVersion::run($media->id);

    expect($disk->written((string) $media->original_path)['visibility'] ?? null)->toBe('public');
});

it('does nothing for a row without a version that waits, or a row that is gone', function (): void {
    $disk = recordMediaDisk();
    $media = Media::factory()->create();

    HidePreviousVersion::run($media->id);
    HidePreviousVersion::run($media->id + 1);

    expect($disk->calls())->toBe([]);
});

it('reports a file the disk does not make private and goes on with the others', function (array $config, string $reported): void {
    Exceptions::fake();
    recordMediaDisk($config);
    $media = Media::factory()->replaced()->create();
    // Only the thumbnail of the version that waits is on the disk; the others cannot be made private.
    Storage::disk('public')->put((string) MediaVersion::waiting($media)?->thumbPath, 'bytes', 'public');

    HidePreviousVersion::run($media->id);

    expect(Storage::disk('public')->getVisibility((string) MediaVersion::waiting($media)?->thumbPath))->toBe('private');
    Exceptions::assertReported($reported);
    Exceptions::assertReportedCount(2);
})->with([
    'a disk that answers false' => [[], MediaNotStored::class],
    'a disk that throws' => [['throw' => true], UnableToSetVisibility::class],
])->skipOnWindows();
