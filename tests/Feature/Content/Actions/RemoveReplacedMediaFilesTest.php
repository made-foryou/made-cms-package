<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;
use Made\CmsPackage\Content\Actions\RemoveReplacedMediaFiles;
use Made\CmsPackage\Content\Models\Media;

beforeEach(function (): void {
    Storage::fake('public');
});

/**
 * A photo with its original and thumbnail on the disk, plus the former files of a change that replaced them.
 *
 * @param  list<string>  $former
 */
function replacedMedia(string $path, array $former): Media
{
    $media = Media::factory()->create([
        'path' => $path,
        'original_path' => 'media/01k0000000000000000000000/original-'.str_repeat('a', 40).'.jpg',
        'thumb_path' => 'media/01k0000000000000000000000/thumb-'.str_repeat('b', 40).'.webp',
    ]);

    foreach ([$media->path, $media->original_path, $media->thumb_path, ...$former] as $file) {
        Storage::disk('public')->put((string) $file, 'bytes');
    }

    return $media;
}

it('removes a former photo next to the original alone, and the former thumbnail', function (): void {
    $home = 'media/01k0000000000000000000000';
    $media = replacedMedia("{$home}/abcdefgh/teamfoto.webp", ["{$home}/teamfoto.webp", "{$home}/thumb-old.webp"]);

    RemoveReplacedMediaFiles::run($media->id, "{$home}/teamfoto.webp", "{$home}/thumb-old.webp");

    expect(Storage::disk('public')->allFiles())->toEqualCanonicalizing([$media->path, $media->original_path, $media->thumb_path]);
});

it('removes the folder of a former crop with everything in it', function (): void {
    $home = 'media/01k0000000000000000000000';
    $media = replacedMedia("{$home}/zyxwvuts/teamfoto.webp", ["{$home}/abcdefgh/teamfoto.webp", "{$home}/abcdefgh/clubavond.webp"]);

    RemoveReplacedMediaFiles::run($media->id, "{$home}/abcdefgh/teamfoto.webp");

    expect(Storage::disk('public')->directoryExists("{$home}/abcdefgh"))->toBeFalse()
        ->and(Storage::disk('public')->allFiles())->toEqualCanonicalizing([$media->path, $media->original_path, $media->thumb_path]);
});

it('removes only the former file from the folder the photo is in now', function (): void {
    $home = 'media/01k0000000000000000000000';
    $media = replacedMedia("{$home}/abcdefgh/clubavond.webp", ["{$home}/abcdefgh/teamfoto.webp"]);

    RemoveReplacedMediaFiles::run($media->id, "{$home}/abcdefgh/teamfoto.webp");

    expect(Storage::disk('public')->allFiles())->toEqualCanonicalizing([$media->path, $media->original_path, $media->thumb_path]);
});

it('leaves every file the row holds now', function (): void {
    $media = replacedMedia('media/01k0000000000000000000000/teamfoto.webp', []);

    RemoveReplacedMediaFiles::run($media->id, $media->path, $media->thumb_path);
    RemoveReplacedMediaFiles::run($media->id, (string) $media->original_path);

    expect(Storage::disk('public')->allFiles())->toEqualCanonicalizing([$media->path, $media->original_path, $media->thumb_path]);
});

it('removes the former files of a row that is gone', function (): void {
    $home = 'media/01k0000000000000000000000';
    $media = replacedMedia("{$home}/teamfoto.webp", ["{$home}/clubavond.webp"]);
    $media->delete();

    RemoveReplacedMediaFiles::run($media->id, "{$home}/clubavond.webp");

    expect(Storage::disk('public')->exists("{$home}/clubavond.webp"))->toBeFalse()
        ->and(Storage::disk('public')->exists($media->path))->toBeTrue();
});

it('leaves every file of the version that waits to be put back', function (): void {
    $media = Media::factory()->replaced()->create();
    $previous = (array) $media->previous;

    foreach ([$media->path, $media->original_path, $media->thumb_path, $previous['path'], $previous['original_path'], $previous['thumb_path']] as $file) {
        Storage::disk('public')->put((string) $file, 'bytes');
    }

    RemoveReplacedMediaFiles::run($media->id, (string) $previous['path'], (string) $previous['thumb_path']);

    expect(Storage::disk('public')->allFiles())->toHaveCount(6);
});

it('never takes the folder of a version that waits for the folder of a crop', function (): void {
    // Version 2 was cropped and committed; version 1 was put back before the cleanup of that crop got the lock.
    $root = 'media/01jc8x0000000000000000000a';
    $media = Media::factory()->create([
        'ulid' => '01jc8x0000000000000000000a',
        'path' => "{$root}/teamfoto.webp",
        'original_path' => "{$root}/original-v1.jpg",
        'thumb_path' => "{$root}/thumb-v1.webp",
        'previous' => [
            'version' => 2,
            'name' => 'nieuw',
            'path' => "{$root}/b2b2b2b2/c3c3c3c3/nieuw.webp",
            'original_path' => "{$root}/b2b2b2b2/original-v2.jpg",
            'thumb_path' => "{$root}/b2b2b2b2/thumb-v2b.webp",
            'mime' => 'image/webp',
            'size' => 100,
        ],
    ]);

    foreach ([$media->path, $media->original_path, $media->thumb_path, "{$root}/b2b2b2b2/nieuw.webp", "{$root}/b2b2b2b2/thumb-v2a.webp", "{$root}/b2b2b2b2/c3c3c3c3/nieuw.webp", "{$root}/b2b2b2b2/original-v2.jpg", "{$root}/b2b2b2b2/thumb-v2b.webp"] as $file) {
        Storage::disk('public')->put((string) $file, 'bytes');
    }

    RemoveReplacedMediaFiles::run($media->id, "{$root}/b2b2b2b2/nieuw.webp", "{$root}/b2b2b2b2/thumb-v2a.webp");

    expect(Storage::disk('public')->allFiles())->toEqualCanonicalizing([
        $media->path,
        $media->original_path,
        $media->thumb_path,
        "{$root}/b2b2b2b2/c3c3c3c3/nieuw.webp",
        "{$root}/b2b2b2b2/original-v2.jpg",
        "{$root}/b2b2b2b2/thumb-v2b.webp",
    ]);
});

it('removes the folder of a former crop of the version in a folder of its own', function (): void {
    $root = 'media/01jc8x0000000000000000000a';
    $media = Media::factory()->create([
        'ulid' => '01jc8x0000000000000000000a',
        'path' => "{$root}/b2b2b2b2/d4d4d4d4/nieuw.webp",
        'original_path' => "{$root}/b2b2b2b2/original-v2.jpg",
        'thumb_path' => "{$root}/b2b2b2b2/thumb-v2.webp",
    ]);

    foreach ([$media->path, $media->original_path, $media->thumb_path, "{$root}/b2b2b2b2/c3c3c3c3/nieuw.webp"] as $file) {
        Storage::disk('public')->put((string) $file, 'bytes');
    }

    RemoveReplacedMediaFiles::run($media->id, "{$root}/b2b2b2b2/c3c3c3c3/nieuw.webp");

    expect(Storage::disk('public')->directoryExists("{$root}/b2b2b2b2/c3c3c3c3"))->toBeFalse()
        ->and(Storage::disk('public')->allFiles())->toEqualCanonicalizing([$media->path, $media->original_path, $media->thumb_path]);
});
