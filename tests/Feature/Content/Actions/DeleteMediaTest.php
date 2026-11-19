<?php

declare(strict_types=1);

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Made\CmsPackage\Content\Actions\DeleteMedia;
use Made\CmsPackage\Content\Actions\RemoveMediaFiles;
use Made\CmsPackage\Content\Actions\ReplaceMedia;
use Made\CmsPackage\Content\Actions\UploadMedia;
use Made\CmsPackage\Content\Data\DeletedMedia;
use Made\CmsPackage\Content\Data\UploadedMedia;
use Made\CmsPackage\Content\Events\MediaDeleted;
use Made\CmsPackage\Content\Models\Media;
use Made\CmsPackage\Content\Models\MediaUsage;
use Made\CmsPackage\Content\Models\Page;

beforeEach(function (): void {
    Storage::fake('public');
});

/**
 * @return list<int>
 */
function idsOf(array $media): array
{
    return array_map(static fn (Media $file): int => $file->id, $media);
}

it('deletes a file nowhere in use with every version of it, also from the disk', function (): void {
    $media = UploadMedia::run(new UploadedMedia(photo(800, 600), 'een.jpg'));
    ReplaceMedia::run($media, new UploadedMedia(photo(800, 600), 'twee.jpg'));
    $other = UploadMedia::run(new UploadedMedia(mediaFixture('wedstrijdschema.pdf'), 'schema.pdf'));

    $result = DeleteMedia::run([$media->id]);

    expect($result)->toBeInstanceOf(DeletedMedia::class)
        ->and(idsOf($result->deleted))->toBe([$media->id])
        ->and($result->kept)->toBe([])
        ->and($result->missing)->toBe([])
        ->and(Media::query()->find($media->id))->toBeNull()
        ->and(Storage::disk('public')->directoryExists("media/{$media->ulid}"))->toBeFalse()
        ->and(Storage::disk('public')->exists($other->path))->toBeTrue();
});

it('keeps a file a page uses, in either version, hidden or in the bin', function (string $state): void {
    $media = Media::factory()->create();
    $page = Page::factory()->create($state === 'deleted' ? ['deleted_at' => now()] : []);
    $usage = MediaUsage::factory()->for($media)->for($page);
    ($state === 'draft' ? $usage->draft() : $usage)->create(['strip_id' => 'a']);

    $result = DeleteMedia::run([$media->id]);

    expect($result->deleted)->toBe([])
        ->and(idsOf($result->kept))->toBe([$media->id])
        ->and(Media::query()->find($media->id))->not->toBeNull();
})->with(['published', 'draft', 'deleted']);

it('keeps a file a page shares as its image, also a page in the bin', function (bool $deleted): void {
    $media = Media::factory()->create();
    $page = Page::factory()->withShareImage($media)->create($deleted ? ['deleted_at' => now()] : []);
    MediaUsage::factory()->for($media)->for($page)->shareImage()->create();

    $result = DeleteMedia::run([$media->id]);

    expect($result->deleted)->toBe([])
        ->and(idsOf($result->kept))->toBe([$media->id])
        ->and(Media::query()->find($media->id))->not->toBeNull();
})->with(['on the site' => false, 'in the bin' => true]);

it('deletes what can go and keeps the rest, and says which ids were gone', function (): void {
    [$free, $used, $alsoFree] = Media::factory()->count(3)->create()->all();
    MediaUsage::factory()->for($used)->create();

    $result = DeleteMedia::run([$alsoFree->id, $used->id, 999_999, $free->id]);

    expect(idsOf($result->deleted))->toBe([$free->id, $alsoFree->id])
        ->and(idsOf($result->kept))->toBe([$used->id])
        ->and($result->missing)->toBe([999_999])
        ->and(Media::query()->pluck('id')->all())->toBe([$used->id]);
});

it('locks the files in the order of their ids before it reads where they are used', function (): void {
    [$a, $b] = Media::factory()->count(2)->create()->all();
    $queries = [];

    DB::listen(static function (QueryExecuted $query) use (&$queries): void {
        if (str_starts_with($query->sql, 'select')) {
            $queries[] = [DB::transactionLevel(), $query->sql];
        }
    });
    $outside = DB::transactionLevel();

    DeleteMedia::run([$b->id, $a->id]);

    expect($queries[0][0])->toBe($outside + 1)
        ->and($queries[0][1])->toContain('from '.quoted('cms_media'))
        ->and($queries[0][1])->toContain('order by '.quoted('id').' asc')
        ->and($queries[1][1])->toContain('from '.quoted('cms_media_usages'));
});

it('tells every deleted file once the change is committed', function (): void {
    Event::fake([MediaDeleted::class]);
    [$free, $used] = Media::factory()->count(2)->create()->all();
    MediaUsage::factory()->for($used)->create();

    DeleteMedia::run([$free->id, $used->id]);

    Event::assertDispatchedTimes(MediaDeleted::class, 1);
    Event::assertDispatched(MediaDeleted::class, fn (MediaDeleted $event): bool => $event->media->id === $free->id);
});

it('takes the files off the disk only once the change is committed', function (): void {
    $media = UploadMedia::run(new UploadedMedia(photo(800, 600), 'een.jpg'));

    DB::beginTransaction();
    DeleteMedia::run([$media->id]);
    $during = Storage::disk('public')->directoryExists("media/{$media->ulid}");
    DB::rollBack();

    expect($during)->toBeTrue()
        ->and(Storage::disk('public')->directoryExists("media/{$media->ulid}"))->toBeTrue()
        ->and(Media::query()->find($media->id))->not->toBeNull();
});

it('takes only the folder of a file off the disk', function (string $ulid): void {
    Storage::disk('public')->put('media/01jc8x0000000000000000000a/een.webp', 'bytes');

    expect(fn () => RemoveMediaFiles::run($ulid))->toThrow(InvalidArgumentException::class)
        ->and(Storage::disk('public')->exists('media/01jc8x0000000000000000000a/een.webp'))->toBeTrue();
})->with([
    'empty' => [''],
    'a path' => ['../01jc8x0000000000000000000a'],
    'capitals' => ['01JC8X0000000000000000000A'],
    'too short' => ['01jc8x'],
]);
