<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Made\CmsPackage\Access\Models\Editor;
use Made\CmsPackage\Content\Actions\ListPages;
use Made\CmsPackage\Content\Actions\SummarizePage;
use Made\CmsPackage\Content\Data\MenuPlace;
use Made\CmsPackage\Content\Enums\MenuPlaceReason;
use Made\CmsPackage\Content\Enums\PageState;
use Made\CmsPackage\Content\Models\Page;

beforeEach(function (): void {
    useStripTypes();
});

it('sums up the strips of the working copy with their fields, a strip of an unknown type counting none', function (): void {
    $page = Page::factory()->published()
        ->withStrips([storedStrip('hero', id: 'a')])
        ->withDraft([
            storedStrip('hero', ['kop' => 'Onze teams'], id: 'a'),
            storedStrip('cards', id: 'b'),
            storedStrip('weg', id: 'c'),
        ])
        ->create();

    $summary = SummarizePage::run($page, ListPages::run());

    expect(array_column($summary->strips->toArray(), 'id'))->toBe(['a', 'b', 'c'])
        ->and($summary->strips->strips[0]->summary)->toBe('Onze teams')
        ->and($summary->fields)->toBe(8);
});

it('sums up a page without strips', function (): void {
    $page = Page::factory()->create();

    $summary = SummarizePage::run($page, ListPages::run());

    expect($summary->strips->strips)->toBe([])
        ->and($summary->fields)->toBe(0);
});

it('says where the page lies in the tree and in the menu of the site', function (): void {
    $home = Page::factory()->published()->at('/')->create(['title' => 'Home']);
    $teams = Page::factory()->published()->under($home)->atPosition(0)->create(['title' => 'Teams']);
    Page::factory()->published()->under($home)->atPosition(1)->create();
    Page::factory()->under($teams)->create();
    Page::factory()->hidden()->published()->under($teams)->create();

    $summary = SummarizePage::run($teams, ListPages::run());

    expect($summary->parentId)->toBe($home->id)
        ->and($summary->parentTitle)->toBe('Home')
        ->and($summary->children)->toBe(2)
        ->and($summary->menuPlace)->toEqual(new MenuPlace(MenuPlaceReason::Position, position: 1, total: 2));
});

it('says a page at the top lies under nothing and a page outside the menu has no place in it', function (): void {
    $page = Page::factory()->inMenu(false)->create();

    $summary = SummarizePage::run($page, ListPages::run());

    expect($summary->parentId)->toBeNull()
        ->and($summary->parentTitle)->toBeNull()
        ->and($summary->children)->toBe(0)
        ->and($summary->menuPlace)->toBeNull();
});

it('gives the state and the type of the page', function (): void {
    usePageTypes();

    $draft = Page::factory()->create(['type' => 'team']);
    $hidden = Page::factory()->published()->hidden()->create();

    expect(SummarizePage::run($draft, ListPages::run()))
        ->state->toBe(PageState::Draft)
        ->type->toBe('team')
        ->and(SummarizePage::run($hidden, ListPages::run()))
        ->state->toBe(PageState::Hidden)
        ->type->toBeNull();
});

it('says when the page was created, published and changed last, and by whom', function (): void {
    $creator = Editor::factory()->create();
    $changer = Editor::factory()->create();
    $page = Page::factory()->published()->create([
        'created_at' => CarbonImmutable::parse('2024-03-12 09:00:00'),
        'created_by' => $creator->id,
        'published_at' => CarbonImmutable::parse('2026-10-01 12:00:00'),
        'updated_at' => CarbonImmutable::parse('2026-10-20 15:30:00'),
        'updated_by' => $changer->id,
    ]);

    $summary = SummarizePage::run($page, ListPages::run());

    expect($summary->createdAt?->toDateTimeString())->toBe('2024-03-12 09:00:00')
        ->and($summary->createdBy)->toBe($creator->id)
        ->and($summary->publishedAt?->toDateTimeString())->toBe('2026-10-01 12:00:00')
        ->and($summary->updatedAt?->toDateTimeString())->toBe('2026-10-20 15:30:00')
        ->and($summary->updatedBy)->toBe($changer->id)
        ->and($summary->editors())->toBe([$creator->id, $changer->id]);
});

it('names every editor once and leaves out the unknown', function (): void {
    $editor = Editor::factory()->create();

    $known = Page::factory()->create(['created_by' => $editor->id, 'updated_by' => $editor->id]);
    $unknown = Page::factory()->create();

    expect(SummarizePage::run($known, ListPages::run())->editors())->toBe([$editor->id])
        ->and(SummarizePage::run($unknown, ListPages::run())->editors())->toBe([]);
});
