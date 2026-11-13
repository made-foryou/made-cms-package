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
