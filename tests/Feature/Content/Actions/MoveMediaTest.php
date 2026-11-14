<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Made\CmsPackage\Content\Actions\MoveMedia;
use Made\CmsPackage\Content\Events\MediaMoved;
use Made\CmsPackage\Content\Exceptions\MediaFolderNotFound;
use Made\CmsPackage\Content\Models\Media;
use Made\CmsPackage\Content\Models\MediaFolder;

it('moves a file into a folder', function (): void {
    $folder = MediaFolder::factory()->create(['name' => 'Teams']);
    $media = Media::factory()->create();

    $media = MoveMedia::run($media, $folder->id);

    expect($media->folder_id)->toBe($folder->id)
        ->and(Media::query()->findOrFail($media->id)->folder_id)->toBe($folder->id);
});

it('moves a file out of its folder, to all files', function (): void {
    $media = Media::factory()->in(MediaFolder::factory()->create())->create();

    MoveMedia::run($media, null);

    expect(Media::query()->findOrFail($media->id)->folder_id)->toBeNull();
});

it('moves the file from the folder it is in now, not the one the editor opened it in', function (): void {
    $teams = MediaFolder::factory()->create(['name' => 'Teams']);
    $opened = Media::factory()->in($teams)->create();
    MoveMedia::run(Media::query()->findOrFail($opened->id), MediaFolder::factory()->create()->id);

    $media = MoveMedia::run($opened, $teams->id);

    expect($media->folder_id)->toBe($teams->id)
        ->and(Media::query()->findOrFail($opened->id)->folder_id)->toBe($teams->id);
});

it('refuses a folder that is gone and leaves the file where it was', function (): void {
    $folder = MediaFolder::factory()->create();
    $media = Media::factory()->in($folder)->create();

    expect(fn (): Media => MoveMedia::run($media, 999))->toThrow(MediaFolderNotFound::class)
        ->and(Media::query()->findOrFail($media->id)->folder_id)->toBe($folder->id);
});

it('tells that a file was moved, and only when it was', function (): void {
    Event::fake([MediaMoved::class]);
    $folder = MediaFolder::factory()->create();
    $media = Media::factory()->in($folder)->create();

    MoveMedia::run($media, $folder->id);
    Event::assertNotDispatched(MediaMoved::class);

    MoveMedia::run($media, null);
    Event::assertDispatched(MediaMoved::class, fn (MediaMoved $event): bool => $event->media->is($media));
});
