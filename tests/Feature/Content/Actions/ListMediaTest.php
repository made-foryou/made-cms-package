<?php

declare(strict_types=1);

use Made\CmsPackage\Content\Actions\ListMedia;
use Made\CmsPackage\Content\Data\MediaFile;
use Made\CmsPackage\Content\Data\MediaFilter;
use Made\CmsPackage\Content\Enums\MediaKind;
use Made\CmsPackage\Content\Enums\MediaSort;
use Made\CmsPackage\Content\Exceptions\MediaFolderNotFound;
use Made\CmsPackage\Content\Models\Media;
use Made\CmsPackage\Content\Models\MediaFolder;
use Made\CmsPackage\Content\Models\MediaUsage;
use Made\CmsPackage\Content\Models\Page;

beforeEach(function (): void {
    config([
        'filesystems.disks.media' => ['driver' => 'local', 'root' => storage_path('app/media'), 'url' => 'https://cdn.example.com'],
        'made-cms-package.media.disk' => 'media',
    ]);
});

/**
 * @return list<int>
 */
function listedIds(MediaFilter $filter = new MediaFilter): array
{
    return array_map(static fn (MediaFile $file): int => $file->id, ListMedia::run($filter)->files);
}

it('lists the files newest first, with the url of the media disk', function (): void {
    $old = Media::factory()->create(['created_at' => now()->subDay(), 'path' => 'media/01k9/oud.webp']);
    $new = Media::factory()->create(['created_at' => now()]);

    $list = ListMedia::run(new MediaFilter);

    expect(listedIds())->toBe([$new->id, $old->id])
        ->and($list->files[1]->url)->toBe('https://cdn.example.com/media/01k9/oud.webp')
        ->and($list->page)->toBe(1)
        ->and($list->total)->toBe(2)
        ->and($list->more)->toBeFalse();
});

it('lists the files by name', function (): void {
    $veld = Media::factory()->create(['name' => 'veld']);
    $clubavond = Media::factory()->create(['name' => 'clubavond']);

    expect(listedIds(new MediaFilter(sort: MediaSort::Name)))->toBe([$clubavond->id, $veld->id]);
});

it('lists the files of a folder', function (): void {
    $teams = MediaFolder::factory()->create();
    $inTeams = Media::factory()->in($teams)->create();
    Media::factory()->create();

    expect(listedIds(new MediaFilter(folderId: $teams->id)))->toBe([$inTeams->id]);
});

it('refuses a folder that is gone', function (): void {
    ListMedia::run(new MediaFilter(folderId: 404));
})->throws(MediaFolderNotFound::class);

it('lists the files of several kinds, which win from a single kind', function (): void {
    Media::factory()->create();
    $document = Media::factory()->document()->create();
    $video = Media::factory()->video()->create();

    expect(listedIds(new MediaFilter(sort: MediaSort::Name, kinds: [MediaKind::Document, MediaKind::Video])))->toEqualCanonicalizing([$document->id, $video->id])
        ->and(listedIds(new MediaFilter(kind: MediaKind::Image, kinds: [MediaKind::Video])))->toBe([$video->id]);
});

it('lists the files of a kind', function (): void {
    $image = Media::factory()->create();
    $document = Media::factory()->document()->create();

    expect(listedIds(new MediaFilter(kind: MediaKind::Image)))->toBe([$image->id])
        ->and(listedIds(new MediaFilter(kind: MediaKind::Document)))->toBe([$document->id]);
});

it('lists the images without an alt text', function (): void {
    $without = Media::factory()->create();
    Media::factory()->withAlt('Het eerste team')->create();
    Media::factory()->document()->create();

    expect(listedIds(new MediaFilter(withoutAlt: true)))->toBe([$without->id]);
});

it('finds files by their name or their alt text', function (): void {
    $byName = Media::factory()->create(['name' => 'teamfoto-merwestad', 'created_at' => now()->subMinute()]);
    $byAlt = Media::factory()->withAlt('Het Merwestad-team op het veld')->create(['name' => 'veld']);
    Media::factory()->create(['name' => 'clubavond']);

    expect(listedIds(new MediaFilter(search: 'merwestad')))->toBe([$byAlt->id, $byName->id]);
});

it('combines the filters', function (): void {
    $teams = MediaFolder::factory()->create();
    $match = Media::factory()->in($teams)->create(['name' => 'teamfoto-1']);
    Media::factory()->in($teams)->withAlt('Het eerste team')->create(['name' => 'teamfoto-2']);
    Media::factory()->in($teams)->document()->create(['name' => 'teamfoto-schema']);
    Media::factory()->create(['name' => 'teamfoto-3']);

    expect(listedIds(new MediaFilter(folderId: $teams->id, search: 'teamfoto', kind: MediaKind::Image, withoutAlt: true)))
        ->toBe([$match->id]);
});

it('gives the files a page at a time, with the total and whether more follow', function (): void {
    Media::factory()->count(ListMedia::PER_PAGE + 2)->create();

    $first = ListMedia::run(new MediaFilter);
    $second = ListMedia::run(new MediaFilter(page: 2));
    $beyond = ListMedia::run(new MediaFilter(page: 3));

    expect($first->files)->toHaveCount(ListMedia::PER_PAGE)
        ->and($first->total)->toBe(ListMedia::PER_PAGE + 2)
        ->and($first->more)->toBeTrue()
        ->and($second->files)->toHaveCount(2)
        ->and($second->page)->toBe(2)
        ->and($second->more)->toBeFalse()
        ->and($beyond->files)->toBe([])
        ->and($beyond->more)->toBeFalse();
});

it('asks nothing of the media disk when no file matches', function (): void {
    config(['made-cms-package.media.disk' => 'nope']);
    Media::factory()->document()->create();

    $list = ListMedia::run(new MediaFilter(kind: MediaKind::Image));

    expect($list->files)->toBe([])->and($list->total)->toBe(0);
});

it('says which files of the page a page uses', function (): void {
    [$free, $used, $onDeletedPage] = Media::factory()->count(3)->create()->all();
    MediaUsage::factory()->for($used)->create();
    MediaUsage::factory()->for($onDeletedPage)->for(Page::factory()->create(['deleted_at' => now()]))->draft()->create();

    expect(ListMedia::run(new MediaFilter)->used)->toBe([$used->id, $onDeletedPage->id])
        ->and($free->id)->not->toBeIn(ListMedia::run(new MediaFilter)->used);
});
