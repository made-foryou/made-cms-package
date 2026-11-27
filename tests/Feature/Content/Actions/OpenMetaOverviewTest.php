<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Made\CmsPackage\Content\Actions\ListAttentionByPage;
use Made\CmsPackage\Content\Actions\OpenMetaOverview;
use Made\CmsPackage\Content\Data\AttentionItem;
use Made\CmsPackage\Content\Data\MetaRoundPlace;
use Made\CmsPackage\Content\Data\MetaRow;
use Made\CmsPackage\Content\Data\MetaSearch;
use Made\CmsPackage\Content\Enums\AttentionReason;
use Made\CmsPackage\Content\Enums\MetaFilter;
use Made\CmsPackage\Content\Models\Page;

beforeEach(function (): void {
    config(['app.url' => 'https://example.test', 'app.name' => 'TTV Merwestad']);
});

/**
 * Four pages in menu order: A without a description, B in order, C with a title that is too long, D without one.
 *
 * @return array{a: Page, b: Page, c: Page, d: Page}
 */
function metaSite(): array
{
    return [
        'a' => Page::factory()->published()->at('/a')->atPosition(1)->create(['title' => 'A']),
        'b' => Page::factory()->published()->at('/b')->atPosition(2)->withMeta('B', 'Een omschrijving van B.')->create(['title' => 'B']),
        'c' => Page::factory()->published()->at('/c')->atPosition(3)->withMeta(str_repeat('c', 61), 'Een omschrijving van C.')->create(['title' => 'C']),
        'd' => Page::factory()->published()->at('/d')->atPosition(4)->create(['title' => 'D']),
    ];
}

it('opens at the pages without a description and the first point', function (): void {
    ['a' => $a, 'c' => $c] = metaSite();

    $overview = OpenMetaOverview::run(new MetaSearch);

    expect($overview->filter)->toBe(MetaFilter::Missing)
        ->and(array_map(static fn (MetaRow $row): string => $row->name, $overview->listed()))->toBe(['A', 'D'])
        ->and($overview->offered())->toBe(MetaFilter::cases())
        ->and($overview->points())->toBe(3)
        ->and($overview->shown())->toBe(4)
        ->and($overview->finished())->toBeFalse()
        ->and($overview->panel?->row->id)->toBe($a->id)
        ->and($overview->panel?->place)->toEqual(new MetaRoundPlace(position: 1, total: 3, done: 0, next: $c->id));
});

it('keeps a filter asked for while it counts a page, and gives way otherwise', function (?MetaFilter $asked, MetaFilter $shown): void {
    Page::factory()->published()->at('/a')->create();
    Page::factory()->published()->at('/b')->withMeta('B', 'Een omschrijving.')->create();

    expect(OpenMetaOverview::run(new MetaSearch(filter: $asked))->filter)->toBe($shown);
})->with([
    'in order' => [MetaFilter::Ok, MetaFilter::Ok],
    'all' => [MetaFilter::All, MetaFilter::All],
    'long, which counts nothing' => [MetaFilter::Long, MetaFilter::Missing],
    'none' => [null, MetaFilter::Missing],
]);

it('opens at the texts that are too long once every page has a description', function (): void {
    Page::factory()->published()->at('/b')->withMeta('B', str_repeat('b', 156))->create();

    expect(OpenMetaOverview::run(new MetaSearch)->filter)->toBe(MetaFilter::Long);
});

it('is finished when every page Google shows is in order, with no page open', function (): void {
    Page::factory()->published()->at('/b')->withMeta('B', 'Een omschrijving.')->create();
    Page::factory()->at('/concept')->create();

    $overview = OpenMetaOverview::run(new MetaSearch);

    expect($overview->filter)->toBe(MetaFilter::Ok)
        ->and($overview->offered())->toBe([MetaFilter::Ok, MetaFilter::All])
        ->and($overview->finished())->toBeTrue()
        ->and($overview->panel)->toBeNull();
});

it('lists all pages when Google shows none', function (): void {
    Page::factory()->at('/concept')->create();

    $overview = OpenMetaOverview::run(new MetaSearch);

    expect($overview->filter)->toBe(MetaFilter::All)
        ->and($overview->shown())->toBe(0)
        ->and($overview->finished())->toBeFalse()
        ->and($overview->panel)->toBeNull();
});

it('opens a point at its place in the round, counting those done and those passed by', function (): void {
    ['c' => $c, 'd' => $d] = metaSite();

    $panel = OpenMetaOverview::run(new MetaSearch(pageId: $c->id, done: 1))->panel;

    expect($panel?->row->id)->toBe($c->id)
        ->and($panel?->place)->toEqual(new MetaRoundPlace(position: 3, total: 4, done: 1, next: $d->id))
        ->and($panel?->place?->left())->toBe(3);
});

it('opens a page that is no point on its own', function (Closure $page): void {
    metaSite();
    $other = $page();

    $panel = OpenMetaOverview::run(new MetaSearch(pageId: $other->id))->panel;

    expect($panel?->row->id)->toBe($other->id)
        ->and($panel?->place)->toBeNull();
})->with([
    'in order' => [static fn (): Page => Page::factory()->published()->at('/e')->withMeta('E', 'Een omschrijving.')->create()],
    'a draft' => [static fn (): Page => Page::factory()->at('/f')->create()],
]);

it('opens the first point for a page that is not there, and counts the points done at most every page', function (): void {
    ['a' => $a] = metaSite();

    $overview = OpenMetaOverview::run(new MetaSearch(pageId: missingId(), done: 999));

    expect($overview->panel?->row->id)->toBe($a->id)
        ->and($overview->done)->toBe(4)
        ->and($overview->panel?->place?->position)->toBe(5)
        ->and($overview->panel?->place?->total)->toBe(7);
});

it('gives the open page its own title, the title without one and what Google shows', function (): void {
    ['c' => $c, 'a' => $a] = metaSite();

    $c = OpenMetaOverview::run(new MetaSearch(pageId: $c->id))->panel;
    $a = OpenMetaOverview::run(new MetaSearch(pageId: $a->id))->panel;

    expect($c?->ownTitle)->toBe(str_repeat('c', 61))
        ->and($c?->fallbackTitle)->toBe('C · TTV Merwestad')
        ->and($c?->meta->description)->toBe('Een omschrijving van C.')
        ->and($c?->meta->canonical)->toBe('https://example.test/c')
        ->and($a?->ownTitle)->toBeNull()
        ->and($a?->meta->title)->toBe('A · TTV Merwestad');
});

it('proposes a description only when the open page needs one', function (): void {
    useStripTypes();
    $strips = [storedStrip('hero', ['intro' => 'We trainen op dinsdag en donderdag in Mountain Network.'])];
    $without = Page::factory()->published()->at('/zonder')->withStrips($strips)->create();
    $with = Page::factory()->published()->at('/met')->withStrips($strips)->withMeta('Met', 'Een omschrijving.')->create();

    expect(OpenMetaOverview::run(new MetaSearch(pageId: $without->id))->panel?->suggestion?->text)
        ->toBe('We trainen op dinsdag en donderdag in Mountain Network.')
        ->and(OpenMetaOverview::run(new MetaSearch(pageId: $with->id))->panel?->suggestion)->toBeNull();
});

it('counts as points exactly the pages that ask attention for their title or description', function (): void {
    metaSite();
    Page::factory()->published()->at('/e')->withMeta('E', str_repeat('e', 156))->create(['title' => 'E']);
    Page::factory()->published()->noindex()->at('/f')->create(['title' => 'F']);

    $points = array_map(static fn (MetaRow $row): int => $row->id, OpenMetaOverview::run(new MetaSearch)->rows->round());
    $attention = ListAttentionByPage::run();
    $meta = [AttentionReason::MetaDescriptionMissing, AttentionReason::MetaTitleTooLong, AttentionReason::MetaDescriptionTooLong];
    $asking = Page::query()->pluck('id')->filter(static fn (int $id): bool => array_filter(
        $attention->of($id)->items,
        static fn (AttentionItem $item): bool => in_array($item->reason, $meta, true),
    ) !== [])->values()->all();

    expect($points)->toEqualCanonicalizing($asking)->toHaveCount(4);
});

it('reads a fixed number of times, however many pages there are', function (int $count): void {
    for ($i = 0; $i < $count; $i++) {
        Page::factory()->published()->at('/pagina-'.$i)->create();
    }

    DB::enableQueryLog();
    OpenMetaOverview::run(new MetaSearch);

    expect(DB::getQueryLog())->toHaveCount(6);
})->with([3, 30]);
