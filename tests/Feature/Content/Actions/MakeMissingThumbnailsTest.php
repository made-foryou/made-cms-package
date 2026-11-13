<?php

declare(strict_types=1);

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Made\CmsPackage\Content\Actions\MakeMissingThumbnails;
use Made\CmsPackage\Content\Data\MadeThumbnails;
use Made\CmsPackage\Content\Exceptions\MediaNotStored;
use Made\CmsPackage\Content\Models\Media;

beforeEach(function (): void {
    Storage::fake('public');
});

/**
 * A photo the library scaled down before it made thumbnails: its WebP on the disk, without a thumbnail.
 */
function photoWithoutThumbnail(string $directory, int $width = 1920, int $height = 1280, ?string $path = null): Media
{
    $path ??= "{$directory}/teamfoto.webp";
    Storage::disk('public')->put($path, (string) file_get_contents(photo($width, $height, 'webp')));

    return Media::factory()->withoutThumbnail()->create([
        'name' => basename($directory),
        'path' => $path,
        'original_path' => "{$directory}/original-abc.jpg",
        'width' => $width,
        'height' => $height,
    ]);
}

it('gives a photo without a thumbnail one, made from the photo the site shows', function (): void {
    $media = photoWithoutThumbnail('media/01k9');

    $made = MakeMissingThumbnails::run();

    $thumb = (string) $media->refresh()->thumb_path;

    expect($made)->toEqual(new MadeThumbnails(made: 1, had: 0, failed: []))
        ->and($thumb)->toMatch('#^media/01k9/thumb-[0-9A-Za-z]{40}\.webp$#')
        ->and(getimagesizefromstring((string) Storage::disk('public')->get($thumb)))->toMatchArray([0 => 540, 1 => 360, 'mime' => 'image/webp']);
});

it('puts the thumbnail next to the original, not in the folder of a cropped photo', function (): void {
    $media = photoWithoutThumbnail('media/01k9', path: 'media/01k9/x7k2m9qa/teamfoto.webp');

    MakeMissingThumbnails::run();

    expect((string) $media->refresh()->thumb_path)->toMatch('#^media/01k9/thumb-[0-9A-Za-z]{40}\.webp$#');
});

it('leaves a photo that has a thumbnail alone', function (): void {
    $media = Media::factory()->create(['thumb_path' => 'media/01k9/thumb-klein.webp']);

    expect(MakeMissingThumbnails::run())->toEqual(new MadeThumbnails(made: 0, had: 1, failed: []))
        ->and($media->refresh()->thumb_path)->toBe('media/01k9/thumb-klein.webp')
        ->and(Storage::disk('public')->allFiles())->toBe([]);
});

it('gives no thumbnail to a file that shows itself', function (): void {
    Media::factory()->create(['path' => 'media/01k9/logo.svg', 'mime' => 'image/svg+xml', 'original_path' => null, 'thumb_path' => null]);
    Media::factory()->create(['path' => 'media/01ka/laden.gif', 'mime' => 'image/gif', 'original_path' => null, 'thumb_path' => null]);
    Media::factory()->document()->create();
    Media::factory()->video()->create();

    expect(MakeMissingThumbnails::run())->toEqual(new MadeThumbnails(made: 0, had: 0, failed: []))
        ->and(Media::query()->whereNotNull('thumb_path')->count())->toBe(0)
        ->and(Storage::disk('public')->allFiles())->toBe([]);
});

it('names a photo that is gone or cannot be read, and goes on with the others', function (): void {
    $gone = Media::factory()->withoutThumbnail()->create(['name' => 'weg', 'path' => 'media/01k1/weg.webp']);
    Storage::disk('public')->put('media/01k2/kapot.webp', 'geen foto');
    $broken = Media::factory()->withoutThumbnail()->create(['name' => 'kapot', 'path' => 'media/01k2/kapot.webp']);
    $fine = photoWithoutThumbnail('media/01k3');

    expect(MakeMissingThumbnails::run())->toEqual(new MadeThumbnails(made: 1, had: 0, failed: [
        ['id' => $gone->id, 'name' => 'weg', 'reason' => 'missing'],
        ['id' => $broken->id, 'name' => 'kapot', 'reason' => 'unreadable'],
    ]))
        ->and($gone->refresh()->thumb_path)->toBeNull()
        ->and($broken->refresh()->thumb_path)->toBeNull()
        ->and($fine->refresh()->thumb_path)->not->toBeNull();
});

it('stops when the disk does not take a thumbnail', function (): void {
    $media = photoWithoutThumbnail('media/01k9');
    $photo = Storage::disk('public')->get($media->path);
    $disk = Mockery::mock(FilesystemAdapter::class);
    $disk->shouldReceive('get')->with($media->path)->andReturn($photo);
    $disk->shouldReceive('put')->andReturnFalse();
    Storage::set('public', $disk);

    expect(fn (): MadeThumbnails => MakeMissingThumbnails::run())->toThrow(MediaNotStored::class);

    expect($media->refresh()->thumb_path)->toBeNull();
});
