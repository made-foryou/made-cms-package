<?php

declare(strict_types=1);

use Illuminate\Contracts\Filesystem\Cloud;
use Illuminate\Support\Facades\Storage;
use Made\CmsPackage\Content\Actions\WriteImageVariants;
use Made\CmsPackage\Content\Data\ImageVariant;
use Made\CmsPackage\Content\Enums\MediaFileRole;
use Made\CmsPackage\Content\Exceptions\MediaNotStored;

it('writes the narrower sizes of a photo into its folder, named after their width', function (): void {
    Storage::fake('public');
    $disk = Storage::disk('public');

    $variants = WriteImageVariants::run($disk, 'media/01k9/x7k2m9qa', (string) file_get_contents(photo(1000, 500, 'webp')));

    expect($variants)->toEqual([
        new ImageVariant('media/01k9/x7k2m9qa/480w.webp', 480, 240),
        new ImageVariant('media/01k9/x7k2m9qa/960w.webp', 960, 480),
    ])->and($disk->allFiles())->toBe(['media/01k9/x7k2m9qa/480w.webp', 'media/01k9/x7k2m9qa/960w.webp']);
});

it('hands the disk each size public with a cache header for a year', function (): void {
    $disk = recordMediaDisk(['visibility' => 'private']);

    WriteImageVariants::run(Storage::disk('public'), 'media/01k9', (string) file_get_contents(photo(1000, 500, 'webp')));

    expect($disk->written('media/01k9/480w.webp'))
        ->toBe(['visibility' => 'public', 'directory_visibility' => 'public', 'CacheControl' => MediaFileRole::CACHE_CONTROL]);
});

it('takes the sizes it wrote off the disk again when the disk does not take one', function (): void {
    $disk = Mockery::mock(Cloud::class);
    $disk->shouldReceive('put')->with('media/01k9/480w.webp', Mockery::any(), Mockery::any())->andReturnTrue();
    $disk->shouldReceive('put')->with('media/01k9/960w.webp', Mockery::any(), Mockery::any())->andReturnFalse();
    $disk->shouldReceive('delete')->with('media/01k9/480w.webp')->once();

    expect(fn (): array => WriteImageVariants::run($disk, 'media/01k9', (string) file_get_contents(photo(1000, 500, 'webp'))))
        ->toThrow(MediaNotStored::class);
});
