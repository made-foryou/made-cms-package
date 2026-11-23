<?php

declare(strict_types=1);

use Made\CmsPackage\Content\Actions\OpenPagesOverview;
use Made\CmsPackage\Content\Data\PageNode;
use Made\CmsPackage\Content\Data\PageSearch;
use Made\CmsPackage\Content\Enums\AttentionReason;
use Made\CmsPackage\Content\Enums\PageFilter;
use Made\CmsPackage\Content\Models\Page;

beforeEach(function (): void {
    config(['app.url' => 'https://example.test', 'app.name' => 'TTV Merwestad']);

    // Published pages Google shows have a description, so only what a test sets asks for attention.
    $home = Page::factory()->atPosition(0)->published()->withMeta(null, 'Welkom.')->withStrips([storedStrip()])->at('/')->create(['title' => 'Home', 'slug' => '']);
    $about = Page::factory()->atPosition(1)->published()->withMeta(null, 'Wie we zijn.')->withStrips([storedStrip()])->withDraft([])->at('/over-ons')->create(['title' => 'Over ons', 'slug' => 'over-ons']);
    $teams = Page::factory()->atPosition(2)->published()->withStrips([storedStrip()])->at('/teams')->create(['title' => 'Onze teams', 'slug' => 'teams']);
    Page::factory()->under($teams)->atPosition(0)->published()->withMeta(null, 'Het eerste.')->withStrips([storedStrip()])->at('/teams/merwestad-1')->create(['title' => 'Merwestad 1', 'slug' => 'merwestad-1']);
    Page::factory()->atPosition(3)->at('/jeugd')->create(['title' => 'Jeugd', 'slug' => 'jeugd']);
    Page::factory()->atPosition(4)->published()->hidden()->withStrips([storedStrip()])->at('/privacy')->create(['title' => 'Privacy', 'slug' => 'privacy']);
    Page::factory()->atPosition(5)->published()->inMenu(false)->withMeta(null, 'Bel ons.')->withStrips([storedStrip()])->at('/contact')->create(['title' => 'Contact', 'slug' => 'contact']);

    $this->ids = Page::query()->pluck('id', 'title')->all();
});

/**
 * @param  list<PageNode>  $nodes
 * @return list<string>
 */
function titlesOf(array $nodes): array
{
    return array_map(static fn (PageNode $node): string => $node->title, $nodes);
}

it('counts every filter over the whole site', function (): void {
    $overview = OpenPagesOverview::run(new PageSearch);

    expect($overview->count(PageFilter::All))->toBe(7)
        ->and($overview->count(PageFilter::Live))->toBe(5)
        ->and($overview->count(PageFilter::Draft))->toBe(2)
        ->and($overview->count(PageFilter::Attention))->toBe(1)
        ->and($overview->count(PageFilter::Hidden))->toBe(1);
});

it('gives the pages of a filter in menu order, for the cards', function (): void {
    $overview = OpenPagesOverview::run(new PageSearch);

    expect(titlesOf($overview->inFilter(PageFilter::Draft)))->toBe(['Over ons', 'Jeugd'])
        ->and(titlesOf($overview->inFilter(PageFilter::Hidden)))->toBe(['Privacy'])
        ->and($overview->attention->reasons())->toBe([AttentionReason::MetaDescriptionMissing]);
});

it('counts the pages in the menu of the site', function (): void {
    expect(OpenPagesOverview::run(new PageSearch)->inSiteMenu)->toBe(4);
});

it('shows the whole tree without a search or a filter', function (): void {
    $overview = OpenPagesOverview::run(new PageSearch);

    expect($overview->search->isFiltered())->toBeFalse()
        ->and(titlesOf($overview->shown->flatten()))->toBe(['Home', 'Over ons', 'Onze teams', 'Merwestad 1', 'Jeugd', 'Privacy', 'Contact'])
        ->and($overview->tree->flatten())->toHaveCount(7);
});

it('shows only the pages of a filter, with the pages above them', function (): void {
    $overview = OpenPagesOverview::run(new PageSearch(filter: PageFilter::Draft));

    expect(titlesOf($overview->shown->flatten()))->toBe(['Over ons', 'Jeugd'])
        ->and($overview->search->isFiltered())->toBeTrue();
});

it('finds a page by its name or its address whatever the capitals, with the pages above it', function (string $text): void {
    $overview = OpenPagesOverview::run(new PageSearch(text: $text));

    expect(titlesOf($overview->shown->flatten()))->toBe(['Onze teams', 'Merwestad 1'])
        ->and($overview->matches($this->ids['Merwestad 1']))->toBeTrue()
        ->and($overview->matches($this->ids['Onze teams']))->toBeFalse();
})->with(['name' => ['MERWESTAD'], 'address' => ['teams/merwestad']]);

it('searches within a filter', function (): void {
    $overview = OpenPagesOverview::run(new PageSearch(text: 'o', filter: PageFilter::Draft));

    expect(titlesOf($overview->shown->flatten()))->toBe(['Over ons']);
});

it('leaves the counts and the cards alone while it searches', function (): void {
    $overview = OpenPagesOverview::run(new PageSearch(text: 'jeugd', filter: PageFilter::Live));

    expect($overview->shown->isEmpty())->toBeTrue()
        ->and($overview->count(PageFilter::All))->toBe(7)
        ->and(titlesOf($overview->inFilter(PageFilter::Draft)))->toBe(['Over ons', 'Jeugd']);
});

it('never lets an empty page stand out as asking for attention', function (): void {
    $overview = OpenPagesOverview::run(new PageSearch(filter: PageFilter::Attention));

    expect(titlesOf($overview->shown->flatten()))->toBe(['Onze teams'])
        ->and($overview->attention->of($this->ids['Jeugd'])->isEmpty())->toBeTrue();
});
