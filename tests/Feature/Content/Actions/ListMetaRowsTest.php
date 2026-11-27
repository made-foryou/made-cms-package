<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Made\CmsPackage\Content\Actions\ListMetaRows;
use Made\CmsPackage\Content\Data\MetaRow;
use Made\CmsPackage\Content\Enums\MetaFilter;
use Made\CmsPackage\Content\Models\Page;

beforeEach(function (): void {
    config(['app.url' => 'https://example.test', 'app.name' => 'TTV Merwestad']);
});

/**
 * @param  list<MetaRow>  $rows
 * @return list<string>
 */
function metaRowNames(array $rows): array
{
    return array_map(static fn (MetaRow $row): string => $row->name, $rows);
}

it('lists every page in menu order with its address and how it does in Google', function (): void {
    $home = Page::factory()->published()->at('/')->atPosition(0)->create(['title' => 'Home']);
    $teams = Page::factory()->published()->at('/teams')->atPosition(1)->create(['title' => 'Onze teams']);
    Page::factory()->published()->under($teams)->at('/teams/jeugd')->create(['title' => 'Jeugd']);
    Page::factory()->published()->at('/contact')->atPosition(2)->inMenu(false)->create(['title' => 'Contact']);

    $rows = ListMetaRows::run();

    expect(metaRowNames($rows->rows))->toBe(['Home', 'Onze teams', 'Jeugd', 'Contact'])
        ->and($rows->rows[1]->path?->value)->toBe('/teams')
        ->and($rows->rows[0]->measure->googleTitle)->toBe('TTV Merwestad')
        ->and($rows->find($home->id)?->name)->toBe('Home')
        ->and($rows->find(missingId()))->toBeNull();
});

it('splits the pages Google shows over missing, long and in order, and counts every page under all', function (): void {
    Page::factory()->published()->at('/a')->atPosition(1)->create(['title' => 'Zonder']);
    Page::factory()->published()->at('/b')->atPosition(2)->withMeta(str_repeat('a', 61), 'Een omschrijving.')->create(['title' => 'Lang']);
    Page::factory()->published()->at('/c')->atPosition(3)->withMeta('Goed', 'Een omschrijving.')->create(['title' => 'Goed']);
    Page::factory()->at('/d')->atPosition(4)->create(['title' => 'Concept']);
    Page::factory()->published()->noindex()->at('/e')->atPosition(5)->create(['title' => 'Intern']);

    $rows = ListMetaRows::run();

    expect(metaRowNames($rows->inFilter(MetaFilter::Missing)))->toBe(['Zonder'])
        ->and(metaRowNames($rows->inFilter(MetaFilter::Long)))->toBe(['Lang'])
        ->and(metaRowNames($rows->inFilter(MetaFilter::Ok)))->toBe(['Goed'])
        ->and($rows->count(MetaFilter::All))->toBe(5);
});

it('works through the points in menu order and finds the next one after any page', function (): void {
    $a = Page::factory()->published()->at('/a')->atPosition(1)->create(['title' => 'A']);
    $b = Page::factory()->published()->at('/b')->atPosition(2)->withMeta('B', 'Een omschrijving.')->create(['title' => 'B']);
    $c = Page::factory()->published()->at('/c')->atPosition(3)->withMeta(str_repeat('c', 61), 'Een omschrijving.')->create(['title' => 'C']);
    $d = Page::factory()->published()->at('/d')->atPosition(4)->create(['title' => 'D']);

    $rows = ListMetaRows::run();

    expect(metaRowNames($rows->round()))->toBe(['A', 'C', 'D'])
        ->and($rows->nextInRound($a->id))->toBe($c->id)
        ->and($rows->nextInRound($b->id))->toBe($c->id)
        ->and($rows->nextInRound($c->id))->toBe($d->id)
        ->and($rows->nextInRound($d->id))->toBeNull()
        ->and($rows->nextInRound(missingId()))->toBe($a->id);
});

it('leaves out a page in the bin', function (): void {
    Page::factory()->published()->at('/weg')->deletedAt(CarbonImmutable::now())->create();
    Page::factory()->published()->at('/blijft')->create(['title' => 'Blijft']);

    expect(metaRowNames(ListMetaRows::run()->rows))->toBe(['Blijft']);
});

it('reads the pages and their addresses a fixed number of times, however many there are', function (int $count): void {
    for ($i = 0; $i < $count; $i++) {
        Page::factory()->published()->at('/pagina-'.$i)->create();
    }

    DB::enableQueryLog();
    ListMetaRows::run();

    expect(DB::getQueryLog())->toHaveCount(4);
})->with([3, 30]);
