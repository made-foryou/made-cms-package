<?php

declare(strict_types=1);

use Made\CmsPackage\Content\Actions\DeletePage;
use Made\CmsPackage\Content\Actions\ListDeletedPages;
use Made\CmsPackage\Content\Data\DeletedPage;
use Made\CmsPackage\Content\Models\Page;

it('lists the deleted pages, most recently deleted first', function (): void {
    $older = Page::factory()->at('/a')->create(['slug' => 'a']);
    $newer = Page::factory()->at('/b')->create(['slug' => 'b']);
    Page::factory()->at('/c')->create(['slug' => 'c']);

    $this->travelTo(now()->subHour(), fn () => DeletePage::run($older));
    DeletePage::run($newer);

    expect(array_map(fn (DeletedPage $page): int => $page->id, ListDeletedPages::run()->pages))->toBe([$newer->id, $older->id]);
});

it('gives the address a page gets back from the pages above it', function (): void {
    $home = Page::factory()->at('/')->create(['slug' => 'home']);
    $about = Page::factory()->at('/over-ons')->create(['slug' => 'over-ons']);
    $team = Page::factory()->under($about)->at('/over-ons/team')->create(['title' => 'Team', 'slug' => 'team']);
    $top = Page::factory()->under($home)->at('/nieuws')->create(['slug' => 'nieuws']);

    DeletePage::run($team);
    DeletePage::run($top);

    $pages = collect(ListDeletedPages::run()->pages)->keyBy('id');

    expect($pages[$team->id]->formerPath->value)->toBe('/over-ons/team')
        ->and($pages[$team->id]->title)->toBe('Team')
        ->and($pages[$team->id]->parentDeleted)->toBeFalse()
        ->and($pages[$top->id]->formerPath->value)->toBe('/nieuws');
});

it('builds the address through deleted pages above it', function (): void {
    $about = Page::factory()->at('/over-ons')->create(['slug' => 'over-ons']);
    $team = Page::factory()->under($about)->at('/over-ons/team')->create(['slug' => 'team']);

    DeletePage::run($team);
    DeletePage::run($about);

    $pages = collect(ListDeletedPages::run()->pages)->keyBy('id');

    expect($pages[$team->id]->formerPath->value)->toBe('/over-ons/team')
        ->and($pages[$team->id]->parentDeleted)->toBeTrue()
        ->and($pages[$about->id]->parentDeleted)->toBeFalse();
});

it('lists no pages that are not deleted', function (): void {
    Page::factory()->at('/a')->create();

    expect(ListDeletedPages::run()->pages)->toBe([]);
});
