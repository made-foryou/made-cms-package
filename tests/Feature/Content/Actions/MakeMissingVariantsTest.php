<?php

declare(strict_types=1);

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Made\CmsPackage\Content\Actions\MakeMissingVariants;
use Made\CmsPackage\Content\Data\MadeVariants;
use Made\CmsPackage\Content\Enums\MediaFileRole;
use Made\CmsPackage\Content\Exceptions\MediaNotStored;
use Made\CmsPackage\Content\Models\Media;

beforeEach(function (): void {
    Storage::fake('public');
});

/**
 * A photo the library scaled down before it made narrower sizes: its WebP on the disk, without sizes.
 */
function photoWithoutVariants(string $directory, int $width = 1920, int $height = 1280, ?string $path = null): Media
{
    $path ??= "{$directory}/teamfoto.webp";
    Storage::disk('public')->put($path, (string) file_get_contents(photo($width, $height, 'webp')));

    return Media::factory()->create([
        'name' => basename($directory),
        'path' => $path,
        'original_path' => "{$directory}/original-abc.jpg",
        'width' => $width,
        'height' => $height,
    ]);
}

it('gives a photo without narrower sizes its sizes next to it, made from the photo the site shows', function (): void {
    $media = photoWithoutVariants('media/01k9', path: 'media/01k9/x7k2m9qa/teamfoto.webp');

    $made = MakeMissingVariants::run();

    expect($made)->toEqual(new MadeVariants(made: 1, had: 0, failed: []))
        ->and($media->refresh()->variants)->toBe([
            ['path' => 'media/01k9/x7k2m9qa/480w.webp', 'width' => 480, 'height' => 320],
            ['path' => 'media/01k9/x7k2m9qa/960w.webp', 'width' => 960, 'height' => 640],
            ['path' => 'media/01k9/x7k2m9qa/1440w.webp', 'width' => 1440, 'height' => 960],
        ])
        ->and(getimagesizefromstring((string) Storage::disk('public')->get('media/01k9/x7k2m9qa/960w.webp')))->toMatchArray([0 => 960, 1 => 640, 'mime' => 'image/webp']);
});

it('counts a photo narrower than every size as made, without files', function (): void {
    $media = photoWithoutVariants('media/01k9', 400, 300);

    expect(MakeMissingVariants::run())->toEqual(new MadeVariants(made: 1, had: 0, failed: []))
        ->and($media->refresh()->variants)->toBe([])
        ->and(Storage::disk('public')->allFiles())->toBe([$media->path]);
});

it('hands the disk the sizes public with a cache header for a year', function (): void {
    $disk = recordMediaDisk(['visibility' => 'private']);
    photoWithoutVariants('media/01k9');

    MakeMissingVariants::run();

    expect($disk->written('media/01k9/480w.webp'))
        ->toBe(['visibility' => 'public', 'directory_visibility' => 'public', 'CacheControl' => MediaFileRole::CACHE_CONTROL]);
});

it('leaves a photo that has its sizes alone, and a file that shows itself', function (): void {
    $media = Media::factory()->withVariants()->create();
    Media::factory()->create(['path' => 'media/01k9/logo.svg', 'mime' => 'image/svg+xml', 'original_path' => null, 'thumb_path' => null]);
    Media::factory()->create(['path' => 'media/01ka/laden.gif', 'mime' => 'image/gif', 'original_path' => null, 'thumb_path' => null]);
    Media::factory()->document()->create();
    Media::factory()->video()->create();
    $variants = $media->variants;

    expect(MakeMissingVariants::run())->toEqual(new MadeVariants(made: 0, had: 1, failed: []))
        ->and($media->refresh()->variants)->toBe($variants)
        ->and(Media::query()->whereNotNull('variants')->count())->toBe(1)
        ->and(Storage::disk('public')->allFiles())->toBe([]);
});

it('names a photo that is gone or cannot be read, and goes on with the others', function (): void {
    $gone = Media::factory()->create(['name' => 'weg', 'path' => 'media/01k1/weg.webp']);
    Storage::disk('public')->put('media/01k2/kapot.webp', 'geen foto');
    $broken = Media::factory()->create(['name' => 'kapot', 'path' => 'media/01k2/kapot.webp']);
    $fine = photoWithoutVariants('media/01k3');

    expect(MakeMissingVariants::run())->toEqual(new MadeVariants(made: 1, had: 0, failed: [
        ['id' => $gone->id, 'name' => 'weg', 'reason' => 'missing'],
        ['id' => $broken->id, 'name' => 'kapot', 'reason' => 'unreadable'],
    ]))
        ->and($gone->refresh()->variants)->toBeNull()
        ->and($broken->refresh()->variants)->toBeNull()
        ->and($fine->refresh()->variants)->toHaveCount(3);
});

it('takes the sizes off the disk again for a photo that was cropped meanwhile', function (): void {
    $media = photoWithoutVariants('media/01k9');
    $photo = Storage::disk('public')->get($media->path);
    $disk = Mockery::mock(FilesystemAdapter::class);
    // The crop lands while the photo is read: it points the row at a photo of its own, with sizes of its own.
    $disk->shouldReceive('get')->with($media->path)->andReturnUsing(static function () use ($media, $photo): ?string {
        Media::query()->whereKey($media->id)->update([
            'path' => 'media/01k9/z9y8x7w6/teamfoto.webp',
            'variants' => json_encode([['path' => 'media/01k9/z9y8x7w6/480w.webp', 'width' => 480, 'height' => 320]]),
        ]);

        return $photo;
    });
    $disk->shouldReceive('put')->andReturnTrue();
    $disk->shouldReceive('delete')->with('media/01k9/480w.webp')->once();
    $disk->shouldReceive('delete')->with('media/01k9/960w.webp')->once();
    $disk->shouldReceive('delete')->with('media/01k9/1440w.webp')->once();
    Storage::set('public', $disk);

    expect(MakeMissingVariants::run())->toEqual(new MadeVariants(made: 0, had: 0, failed: []))
        ->and($media->refresh()->variants)->toBe([['path' => 'media/01k9/z9y8x7w6/480w.webp', 'width' => 480, 'height' => 320]]);
});

it('stops when the disk does not take a size', function (): void {
    $media = photoWithoutVariants('media/01k9');
    $photo = Storage::disk('public')->get($media->path);
    $disk = Mockery::mock(FilesystemAdapter::class);
    $disk->shouldReceive('get')->with($media->path)->andReturn($photo);
    $disk->shouldReceive('put')->andReturnFalse();
    Storage::set('public', $disk);

    expect(fn (): MadeVariants => MakeMissingVariants::run())->toThrow(MediaNotStored::class);

    expect($media->refresh()->variants)->toBeNull();
});
