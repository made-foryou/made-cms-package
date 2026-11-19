<?php

declare(strict_types=1);

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Made\CmsPackage\Content\Actions\MoveMedia;
use Made\CmsPackage\Content\Events\MediaMoved;
use Made\CmsPackage\Content\Exceptions\MediaFolderNotFound;
use Made\CmsPackage\Content\Models\Media;
use Made\CmsPackage\Content\Models\MediaFolder;

it('moves a file into a folder', function (): void {
    $folder = MediaFolder::factory()->create(['name' => 'Teams']);
    $media = Media::factory()->create();

    $moved = MoveMedia::run([$media->id], $folder->id);

    expect($moved)->toHaveCount(1)
        ->and($moved[0]->folder_id)->toBe($folder->id)
        ->and(Media::query()->findOrFail($media->id)->folder_id)->toBe($folder->id);
});

it('moves a file out of its folder, to all files', function (): void {
    $media = Media::factory()->in(MediaFolder::factory()->create())->create();

    MoveMedia::run([$media->id], null);

    expect(Media::query()->findOrFail($media->id)->folder_id)->toBeNull();
});

it('moves more files at once, from whatever folder each is in, and gives them by id', function (): void {
    $teams = MediaFolder::factory()->create(['name' => 'Teams']);
    $news = MediaFolder::factory()->create(['name' => 'Nieuws']);
    $first = Media::factory()->in($news)->create();
    $second = Media::factory()->create();
    $third = Media::factory()->in($teams)->create();

    $moved = MoveMedia::run([$third->id, $first->id, $second->id], $teams->id);

    expect(array_map(static fn (Media $media): int => $media->id, $moved))->toBe([$first->id, $second->id, $third->id])
        ->and(array_map(static fn (Media $media): ?int => $media->folder_id, $moved))->toBe([$teams->id, $teams->id, $teams->id])
        ->and(Media::query()->inFolder($teams)->count())->toBe(3);
});

it('leaves out a file that is gone', function (): void {
    $folder = MediaFolder::factory()->create();
    $media = Media::factory()->create();

    $moved = MoveMedia::run([$media->id, missingId()], $folder->id);

    expect(array_map(static fn (Media $media): int => $media->id, $moved))->toBe([$media->id])
        ->and(Media::query()->findOrFail($media->id)->folder_id)->toBe($folder->id);
});

it('refuses a folder that is gone and leaves every file where it was', function (): void {
    $folder = MediaFolder::factory()->create();
    $first = Media::factory()->in($folder)->create();
    $second = Media::factory()->create();

    expect(fn (): array => MoveMedia::run([$first->id, $second->id], missingId()))->toThrow(MediaFolderNotFound::class)
        ->and(Media::query()->findOrFail($first->id)->folder_id)->toBe($folder->id)
        ->and(Media::query()->findOrFail($second->id)->folder_id)->toBeNull();
});

it('moves nothing when one file cannot be stored', function (): void {
    $folder = MediaFolder::factory()->create();
    $first = Media::factory()->create();
    $second = Media::factory()->create();
    Media::updating(static function (Media $media) use ($second): void {
        if ($media->id === $second->id) {
            throw new RuntimeException('Refused.');
        }
    });

    expect(fn (): array => MoveMedia::run([$first->id, $second->id], $folder->id))->toThrow(RuntimeException::class, 'Refused.')
        ->and(Media::query()->findOrFail($first->id)->folder_id)->toBeNull();
});

it('reads the folder and the files inside its transaction', function (): void {
    $folder = MediaFolder::factory()->create();
    $media = Media::factory()->count(2)->create();
    $outside = DB::transactionLevel();
    $levels = [];

    DB::listen(static function (QueryExecuted $query) use (&$levels): void {
        if (str_starts_with($query->sql, 'select')) {
            $levels[] = DB::transactionLevel();
        }
    });

    MoveMedia::run($media->modelKeys(), $folder->id);

    expect($levels)->toHaveCount(2)
        ->and(array_unique($levels))->toBe([$outside + 1]);
});

it('tells that a file was moved, once for each file and only when it was', function (): void {
    Event::fake([MediaMoved::class]);
    $folder = MediaFolder::factory()->create();
    $inside = Media::factory()->in($folder)->create();
    $outside = Media::factory()->create();

    MoveMedia::run([$inside->id, $outside->id], $folder->id);
    Event::assertDispatchedTimes(MediaMoved::class, 1);
    Event::assertDispatched(MediaMoved::class, fn (MediaMoved $event): bool => $event->media->is($outside) && $event->media->folder_id === $folder->id);

    MoveMedia::run([$inside->id], $folder->id);
    Event::assertDispatchedTimes(MediaMoved::class, 1);
});
