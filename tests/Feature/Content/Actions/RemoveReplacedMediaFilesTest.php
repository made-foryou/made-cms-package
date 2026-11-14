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
