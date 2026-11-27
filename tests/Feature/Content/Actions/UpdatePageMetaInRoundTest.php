<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Made\CmsPackage\Content\Actions\UpdatePageMetaInRound;
use Made\CmsPackage\Content\Data\MetaRoundStep;
use Made\CmsPackage\Content\Data\PageMetaChanges;
use Made\CmsPackage\Content\Events\PageMetaUpdated;
use Made\CmsPackage\Content\Models\Page;

beforeEach(function (): void {
    config(['app.url' => 'https://example.test', 'app.name' => 'TTV Merwestad']);
});

it('puts a point in order and gives the next one', function (): void {
    $a = Page::factory()->published()->at('/a')->atPosition(1)->create(['title' => 'A']);
    Page::factory()->published()->at('/b')->atPosition(2)->withMeta('B', 'Een omschrijving.')->create();
    $c = Page::factory()->published()->at('/c')->atPosition(3)->create();

    $step = UpdatePageMetaInRound::run($a, new PageMetaChanges(title: null, description: 'Een omschrijving van A.'));

    expect($step)->toEqual(new MetaRoundStep(inRound: true, settled: true, next: $c->id))
        ->and($a->refresh()->meta_description)->toBe('Een omschrijving van A.');
});

it('keeps a point that is still too long in the round', function (): void {
    $a = Page::factory()->published()->at('/a')->atPosition(1)->create();
    $b = Page::factory()->published()->at('/b')->atPosition(2)->create();

    $step = UpdatePageMetaInRound::run($a, new PageMetaChanges(title: null, description: str_repeat('a', 156)));

    expect($step)->toEqual(new MetaRoundStep(inRound: true, settled: false, next: $b->id));
});

it('has no next point after the last', function (): void {
    $a = Page::factory()->published()->at('/a')->create();

    expect(UpdatePageMetaInRound::run($a, new PageMetaChanges(title: null, description: 'Een omschrijving van A.')))
        ->toEqual(new MetaRoundStep(inRound: true, settled: true, next: null));
});

it('saves a page that is no point on its own', function (Closure $page): void {
    Page::factory()->published()->at('/a')->create();

    expect(UpdatePageMetaInRound::run($page(), new PageMetaChanges(title: 'Nieuw', description: 'Een omschrijving.')))
        ->toEqual(new MetaRoundStep(inRound: false, settled: false, next: null));
})->with([
    'in order' => [static fn (): Page => Page::factory()->published()->at('/b')->withMeta('B', 'Een omschrijving.')->create()],
    'a draft' => [static fn (): Page => Page::factory()->at('/concept')->create()],
]);

it('leaves the switches of the page and tells that its meta changed', function (): void {
    $page = Page::factory()->published()->outOfSitemap()->at('/a')->create();
    Event::fake([PageMetaUpdated::class]);

    UpdatePageMetaInRound::run($page, new PageMetaChanges(title: 'A', description: 'Een omschrijving van A.'));

    expect($page->refresh()->in_sitemap)->toBeFalse()
        ->and($page->indexable)->toBeTrue();
    Event::assertDispatched(PageMetaUpdated::class);
});
