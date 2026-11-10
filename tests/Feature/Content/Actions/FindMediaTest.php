<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Made\CmsPackage\Content\Actions\FindMedia;
use Made\CmsPackage\Content\Data\MediaFile;
use Made\CmsPackage\Content\Enums\MediaKind;
use Made\CmsPackage\Content\Exceptions\MediaDiskNotUsable;
use Made\CmsPackage\Content\Models\Media;
use Made\CmsPackage\Content\Models\MediaFolder;

beforeEach(function (): void {
    config([
        'filesystems.disks.media' => ['driver' => 'local', 'root' => storage_path('app/media'), 'url' => 'https://cdn.example.com'],
        'made-cms-package.media.disk' => 'media',
    ]);
});

it('finds the files by id with the url of the media disk, never their place on the disk', function (): void {
    $folder = MediaFolder::factory()->create(['name' => 'Teams']);
    $media = Media::factory()->in($folder)->withAlt('Het eerste team')->create([
        'name' => 'teamfoto',
        'path' => 'media/01k9/teamfoto.webp',
        'original_path' => 'media/01k9/original-abc.jpg',
        'size' => 412_000,
        'width' => 1600,
        'height' => 1067,
        'focal_x' => 0.25,
        'focal_y' => 0.75,
    ]);

    expect(FindMedia::run([$media->id])->find($media->id))->toEqual(new MediaFile(
        id: $media->id,
        kind: MediaKind::Image,
        url: 'https://cdn.example.com/media/01k9/teamfoto.webp',
        name: 'teamfoto',
        mime: 'image/webp',
        size: 412_000,
        width: 1600,
        height: 1067,
        alt: 'Het eerste team',
        focalX: 0.25,
        focalY: 0.75,
        folder: 'Teams',
    ));
});

it('finds documents and videos too, which are no image', function (): void {
    $document = Media::factory()->document()->create();
    $files = FindMedia::run([$document->id]);

    expect($files->find($document->id)?->kind)->toBe(MediaKind::Document)
        ->and($files->image($document->id))->toBeNull();
});

it('leaves out the files that are gone', function (): void {
    $media = Media::factory()->create();

    expect(array_keys(FindMedia::run([$media->id, 999])->files))->toBe([$media->id]);
});

it('finds every file with one query for the files and one for their folders', function (): void {
    $folder = MediaFolder::factory()->create();
    $ids = Media::factory()->count(3)->in($folder)->create()->modelKeys();

    DB::enableQueryLog();
    $files = FindMedia::run([...$ids, $ids[0]]);

    expect(DB::getQueryLog())->toHaveCount(2)
        ->and($files->files)->toHaveCount(3);
});

it('asks nothing of the database or the disk without ids, so a site without images never needs the media disk', function (): void {
    config(['made-cms-package.media.disk' => 'nope']);

    DB::enableQueryLog();

    expect(FindMedia::run([])->files)->toBe([])
        ->and(DB::getQueryLog())->toBe([]);
});

it('refuses a media disk a visitor cannot open once there is a file to find', function (): void {
    $media = Media::factory()->create();
    config(['made-cms-package.media.disk' => 'nope']);

    expect(fn () => FindMedia::run([$media->id]))->toThrow(MediaDiskNotUsable::class);
});
