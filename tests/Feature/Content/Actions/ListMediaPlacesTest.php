<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Made\CmsPackage\Content\Actions\ListMediaPlaces;
use Made\CmsPackage\Content\Actions\SyncMediaUsage;
use Made\CmsPackage\Content\Data\MediaPlace;
use Made\CmsPackage\Content\Enums\UsageVersion;
use Made\CmsPackage\Content\Models\Media;
use Made\CmsPackage\Content\Models\MediaUsage;
use Made\CmsPackage\Content\Models\Page;

beforeEach(function (): void {
    useStripTypes();
});

/**
 * The places as the library shows them: "page title: where".
 *
 * @return list<string>
 */
function placeLines(Media $media): array
{
    return array_map(
        static fn (MediaPlace $place): string => "{$place->pageTitle}: {$place->where}",
        ListMediaPlaces::run($media)->places,
    );
}

it('names a place by the name of its strip and the labels of its field, an item of a list by its number', function (): void {
    $media = Media::factory()->create();
    $page = Page::factory()->withStrips([
        storedStrip('hero', ['foto' => $media->id], id: 'h'),
        storedStrip('cards', ['kaarten' => [['id' => 'k1', 'titel' => 'Een'], ['id' => 'k2', 'titel' => 'Twee'], ['id' => 'k3', 'icoon' => $media->id]]], id: 'c'),
    ])->create(['title' => 'Onze teams']);
    SyncMediaUsage::run($page);

    $places = ListMediaPlaces::run($media);

    expect($places->places)->toHaveCount(2)
        ->and($places->places[0])->toEqual(new MediaPlace(
            page: $page->id,
            pageTitle: 'Onze teams',
            where: 'Hero › Foto',
            strip: 'h',
            field: 'foto',
            versions: [UsageVersion::Published],
            pageDeleted: false,
        ))
        ->and($places->places[1]->where)->toBe('Kaarten › Item 3 › Icoon')
        ->and($places->places[1]->field)->toBe('kaarten.k3.icoon');
});

it('names a place by its keys when its type left the catalogue or no longer has the field', function (): void {
    $media = Media::factory()->create();
    $page = Page::factory()->create(['title' => 'Over ons']);
    MediaUsage::factory()->for($media)->for($page)->create(['strip_id' => 'o', 'strip_type' => 'oud', 'field' => 'beeld']);
    MediaUsage::factory()->for($media)->for($page)->create(['strip_id' => 'h', 'strip_type' => 'hero', 'field' => 'logo']);

    expect(placeLines($media))->toBe(['Over ons: hero › logo', 'Over ons: oud › beeld']);
});

it('names the share image of a page as a place of the page itself, before the places of its strips', function (): void {
    $media = Media::factory()->create();
    $page = Page::factory()->published()->withShareImage($media)
        ->withStrips([storedStrip('hero', ['foto' => $media->id], id: 'h')])
        ->create(['title' => 'Over ons']);
    SyncMediaUsage::run($page);

    $places = ListMediaPlaces::run($media)->places;

    expect(placeLines($media))->toBe(['Over ons: Deelafbeelding', 'Over ons: Hero › Foto'])
        ->and($places[0])->toEqual(new MediaPlace(
            page: $page->id,
            pageTitle: 'Over ons',
            where: 'Deelafbeelding',
            strip: '',
            field: 'share_image',
            versions: [UsageVersion::Page],
            pageDeleted: false,
            pagePublished: true,
        ))
        ->and($places[0]->ofPage())->toBeTrue()
        ->and($places[1]->ofPage())->toBeFalse();
});

it('never takes a strip without an id for the share image of its page', function (): void {
    $media = Media::factory()->create();
    $page = Page::factory()->withShareImage($media)->create(['title' => 'Over ons']);
    MediaUsage::factory()->for($media)->for($page)->shareImage()->create();
    MediaUsage::factory()->for($media)->for($page)->create(['strip_id' => '', 'strip_type' => 'oud', 'field' => 'share_image']);

    expect(placeLines($media))->toBe(['Over ons: Deelafbeelding', 'Over ons: oud › share_image']);
});

it('says whether the page of a place is on the site, for the places of the page itself', function (string $state, bool $shown): void {
    $media = Media::factory()->create();
    $page = match ($state) {
        'published' => Page::factory()->published(),
        'draft' => Page::factory(),
        'deleted' => Page::factory()->published()->state(['deleted_at' => now()]),
    };
    $page = $page->withShareImage($media)->create();
    MediaUsage::factory()->for($media)->for($page)->shareImage()->create();

    expect(ListMediaPlaces::run($media)->places[0]->shownOnSite())->toBe($shown);
})->with([
    'a published page' => ['published', true],
    'a page that was never published' => ['draft', false],
    'a page in the bin' => ['deleted', false],
]);

it('says a place in the published version of a page in the bin is not on the site', function (): void {
    $media = Media::factory()->create();
    $page = Page::factory()->published()->create(['deleted_at' => now()]);
    MediaUsage::factory()->for($media)->for($page)->create();
    $live = Page::factory()->published()->create();
    MediaUsage::factory()->for($media)->for($live)->create();

    $places = ListMediaPlaces::run($media)->places;
    $shown = array_map(static fn (MediaPlace $place): bool => $place->shownOnSite(), $places);

    expect(array_combine(array_map(static fn (MediaPlace $place): int => $place->page, $places), $shown))
        ->toEqual([$page->id => false, $live->id => true]);
});

it('gives a place in both versions once, with the versions it is in', function (): void {
    $media = Media::factory()->create();
    $strip = storedStrip('hero', ['foto' => $media->id], id: 'h');
    $page = Page::factory()->published()
        ->withStrips([$strip])
        ->withDraft([$strip, storedStrip('cards', ['kaarten' => [['icoon' => $media->id]]], id: 'c')])
        ->create();
    SyncMediaUsage::run($page);

    $places = ListMediaPlaces::run($media)->places;

    expect($places)->toHaveCount(2)
        ->and($places[0]->versions)->toBe([UsageVersion::Draft, UsageVersion::Published])
        ->and($places[0]->in(UsageVersion::Published))->toBeTrue()
        ->and($places[1]->versions)->toBe([UsageVersion::Draft])
        ->and($places[1]->in(UsageVersion::Published))->toBeFalse();
});

it('lists the pages by title and the places of a page in the order of its strips and their items', function (): void {
    $media = Media::factory()->create();
    $teams = Page::factory()->withStrips([
        storedStrip('cards', ['kaarten' => [['id' => 'z', 'icoon' => $media->id], ['id' => 'a', 'icoon' => $media->id]]], id: 'c'),
        storedStrip('hero', ['foto' => $media->id], id: 'h'),
    ])->create(['title' => 'Onze teams']);
    $news = Page::factory()->withStrips([storedStrip('hero', ['foto' => $media->id], id: 'n')])->create(['title' => 'clubkampioenschappen 2026']);
    SyncMediaUsage::run($teams);
    SyncMediaUsage::run($news);

    expect(placeLines($media))->toBe([
        'clubkampioenschappen 2026: Hero › Foto',
        'Onze teams: Kaarten › Item 1 › Icoon',
        'Onze teams: Kaarten › Item 2 › Icoon',
        'Onze teams: Hero › Foto',
    ]);
});

it('marks a place on a deleted page and counts the pages', function (): void {
    $media = Media::factory()->create();
    $page = Page::factory()->withStrips([storedStrip('hero', ['foto' => $media->id], id: 'h')])->create(['title' => 'Onze teams']);
    $gone = Page::factory()->withStrips([storedStrip('hero', ['foto' => $media->id], id: 'g'), storedStrip('cards', ['kaarten' => [['icoon' => $media->id]]], id: 'c')])->create(['title' => 'Oud nieuws']);
    SyncMediaUsage::run($page);
    SyncMediaUsage::run($gone);
    $gone->delete();

    $places = ListMediaPlaces::run($media);

    expect(array_map(static fn (MediaPlace $place): bool => $place->pageDeleted, $places->places))->toBe([false, true, true])
        ->and($places->count())->toBe(3)
        ->and($places->pageCount())->toBe(2)
        ->and($places->pageCount(withDeleted: false))->toBe(1)
        ->and($places->isEmpty())->toBeFalse();
});

it('numbers an item that moved in the draft from the draft, and one only the published page holds from there', function (): void {
    $media = Media::factory()->create();
    $page = Page::factory()->published()
        ->withStrips([storedStrip('cards', ['kaarten' => [['id' => 'x', 'icoon' => $media->id], ['id' => 'y'], ['id' => 'w', 'icoon' => $media->id]]], id: 'c')])
        ->withDraft([storedStrip('cards', ['kaarten' => [['id' => 'y'], ['id' => 'x', 'icoon' => $media->id]]], id: 'c')])
        ->create(['title' => 'Onze teams']);
    SyncMediaUsage::run($page);

    $places = ListMediaPlaces::run($media)->places;

    expect(array_map(static fn (MediaPlace $place): string => $place->where, $places))->toBe(['Kaarten › Item 2 › Icoon', 'Kaarten › Item 3 › Icoon'])
        ->and($places[0]->versions)->toBe([UsageVersion::Draft, UsageVersion::Published])
        ->and($places[1]->versions)->toBe([UsageVersion::Published]);
});

it('names a place of an item the values no longer hold by its keys', function (): void {
    $media = Media::factory()->create();
    $page = Page::factory()->withStrips([storedStrip('cards', ['kaarten' => []], id: 'c')])->create(['title' => 'Onze teams']);
    MediaUsage::factory()->for($media)->for($page)->create(['strip_id' => 'c', 'strip_type' => 'cards', 'field' => 'kaarten.weg.icoon']);

    expect(placeLines($media))->toBe(['Onze teams: cards › kaarten.weg.icoon']);
});

it('lists the places on a deleted page also for a file loaded with its places in another way', function (): void {
    $media = Media::factory()->create();
    $gone = Page::factory()->withStrips([storedStrip('hero', ['foto' => $media->id], id: 'g')])->create(['title' => 'Oud nieuws']);
    SyncMediaUsage::run($gone);
    $gone->delete();

    $loaded = Media::query()->with('usages.page')->findOrFail($media->id);
    $places = ListMediaPlaces::run($loaded);

    expect($places->isEmpty())->toBeFalse()
        ->and($places->places[0]->pageDeleted)->toBeTrue()
        ->and(Media::query()->inUse()->whereKey($media->id)->exists())->toBeTrue();
});

it('has no places for a file that is used nowhere', function (): void {
    $places = ListMediaPlaces::run(Media::factory()->create());

    expect($places->places)->toBe([])
        ->and($places->isEmpty())->toBeTrue()
        ->and($places->pageCount())->toBe(0);
});

it('reads the places a file was loaded with, without asking again', function (): void {
    $media = Media::factory()->create();
    $page = Page::factory()->withStrips([storedStrip('hero', ['foto' => $media->id], id: 'h')])->create();
    SyncMediaUsage::run($page);
    $loaded = Media::query()->withUsage()->findOrFail($media->id);

    DB::enableQueryLog();
    $places = ListMediaPlaces::run($loaded);

    expect(DB::getQueryLog())->toBe([])
        ->and($places->count())->toBe(1);
});
