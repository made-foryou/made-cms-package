<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Made\CmsPackage\Content\Actions\UpdatePage;
use Made\CmsPackage\Content\Data\PageChanges;
use Made\CmsPackage\Content\Enums\PageStatus;
use Made\CmsPackage\Content\Events\PageUpdated;
use Made\CmsPackage\Content\Exceptions\CircularPageMove;
use Made\CmsPackage\Content\Exceptions\HomePageProtected;
use Made\CmsPackage\Content\Exceptions\InvalidPageSlug;
use Made\CmsPackage\Content\Exceptions\PagePathTaken;
use Made\CmsPackage\Content\Models\Page;

it('changes the title and the menu flag without touching the address', function (): void {
    $page = Page::factory()->at('/over-ons')->create(['title' => 'Over ons', 'slug' => 'over-ons']);

    UpdatePage::run($page, new PageChanges(title: ' Wie we zijn ', slug: 'over-ons', parentId: null, inMenu: false));

    expect($page->refresh()->title)->toBe('Wie we zijn')
        ->and($page->in_menu)->toBeFalse()
        ->and($page->route?->path)->toBe('/over-ons');
});

it('changes the slug and rewrites the addresses of the page and the pages under it', function (): void {
    $about = Page::factory()->at('/over-ons')->create(['slug' => 'over-ons']);
    $team = Page::factory()->under($about)->at('/over-ons/team')->create(['slug' => 'team']);

    UpdatePage::run($about, new PageChanges(title: 'Vereniging', slug: 'vereniging', parentId: null, inMenu: true));

    expect($about->refresh()->slug)->toBe('vereniging')
        ->and($about->route?->path)->toBe('/vereniging')
        ->and($team->refresh()->route?->path)->toBe('/vereniging/team');
});

it('moves the page under another parent, last among its new siblings', function (): void {
    $about = Page::factory()->atPosition(0)->at('/over-ons')->create(['slug' => 'over-ons']);
    $club = Page::factory()->atPosition(1)->at('/vereniging')->create(['slug' => 'vereniging']);
    Page::factory()->under($club)->at('/vereniging/bestuur')->create(['slug' => 'bestuur']);
    $team = Page::factory()->under($about)->at('/over-ons/team')->create(['slug' => 'team']);

    UpdatePage::run($team, new PageChanges(title: 'Team', slug: 'team', parentId: $club->id, inMenu: true));

    expect($team->refresh()->parent_id)->toBe($club->id)
        ->and($team->position)->toBe(1)
        ->and($team->route?->path)->toBe('/vereniging/team');
});

it('moves the page and changes its slug at once', function (): void {
    $about = Page::factory()->atPosition(0)->at('/over-ons')->create(['slug' => 'over-ons']);
    $team = Page::factory()->under($about)->at('/over-ons/team')->create(['slug' => 'team']);

    UpdatePage::run($team, new PageChanges(title: 'Ons team', slug: 'ons-team', parentId: null, inMenu: true));

    expect($team->refresh()->route?->path)->toBe('/ons-team');
});

it('derives the slug from the title when the slug is cleared', function (): void {
    $page = Page::factory()->at('/oud')->create(['slug' => 'oud']);

    UpdatePage::run($page, new PageChanges(title: 'Nieuwe naam', slug: '', parentId: null, inMenu: true));

    expect($page->refresh()->route?->path)->toBe('/nieuwe-naam');
});

it('refuses a slug with nothing left', function (): void {
    $page = Page::factory()->at('/oud')->create(['slug' => 'oud']);

    expect(fn (): Page => UpdatePage::run($page, new PageChanges(title: '???', slug: null, parentId: null, inMenu: true)))
        ->toThrow(InvalidPageSlug::class);
});

it('refuses a slug whose address is taken and changes nothing', function (): void {
    Page::factory()->at('/contact')->create(['slug' => 'contact']);
    $page = Page::factory()->at('/oud')->create(['title' => 'Oud', 'slug' => 'oud']);

    expect(fn (): Page => UpdatePage::run($page, new PageChanges(title: 'Nieuw', slug: 'contact', parentId: null, inMenu: true)))
        ->toThrow(PagePathTaken::class);

    expect($page->refresh()->title)->toBe('Oud')
        ->and($page->route?->path)->toBe('/oud');
});

it('allows a slug that a page under another parent uses', function (): void {
    $about = Page::factory()->at('/over-ons')->create(['slug' => 'over-ons']);
    Page::factory()->under($about)->at('/over-ons/team')->create(['slug' => 'team']);
    $page = Page::factory()->at('/oud')->create(['slug' => 'oud']);

    UpdatePage::run($page, new PageChanges(title: 'Team', slug: 'team', parentId: null, inMenu: true));

    expect($page->refresh()->route?->path)->toBe('/team');
});

it('refuses a parent under the page itself', function (): void {
    $about = Page::factory()->at('/over-ons')->create(['slug' => 'over-ons']);
    $team = Page::factory()->under($about)->at('/over-ons/team')->create(['slug' => 'team']);

    expect(fn (): Page => UpdatePage::run($about, new PageChanges(title: 'Over ons', slug: 'over-ons', parentId: $team->id, inMenu: true)))
        ->toThrow(CircularPageMove::class);
});

it('keeps the address of the home page', function (): void {
    $home = Page::factory()->at('/')->create(['slug' => 'home']);

    expect(fn (): Page => UpdatePage::run($home, new PageChanges(title: 'Home', slug: 'start', parentId: null, inMenu: true)))
        ->toThrow(HomePageProtected::class);
});

it('changes the title of the home page', function (): void {
    $home = Page::factory()->at('/')->create(['title' => 'Home', 'slug' => 'home']);

    UpdatePage::run($home, new PageChanges(title: 'Welkom', slug: 'home', parentId: null, inMenu: true));

    expect($home->refresh()->title)->toBe('Welkom')
        ->and($home->route?->path)->toBe('/');
});

it('does nothing when nothing changes', function (): void {
    Event::fake([PageUpdated::class]);
    $page = Page::factory()->at('/over-ons')->create(['title' => 'Over ons', 'slug' => 'over-ons']);

    UpdatePage::run($page, new PageChanges(title: 'Over ons', slug: 'over-ons', parentId: null, inMenu: true));

    Event::assertNotDispatched(PageUpdated::class);
});

it('keeps the status and the strips', function (): void {
    $strips = [storedStrip(id: 'a')];
    $page = Page::factory()->published()->withStrips($strips)->at('/over-ons')->create(['slug' => 'over-ons']);

    UpdatePage::run($page, new PageChanges(title: 'Anders', slug: 'anders', parentId: null, inMenu: true));

    expect($page->refresh()->status)->toBe(PageStatus::Published)
        ->and($page->strips)->toBe($strips)
        ->and($page->draft_strips)->toBeNull();
});

it('dispatches an event with the old and the new address', function (): void {
    Event::fake([PageUpdated::class]);
    $page = Page::factory()->at('/oud')->create(['slug' => 'oud']);

    UpdatePage::run($page, new PageChanges(title: 'Nieuw', slug: 'nieuw', parentId: null, inMenu: true));

    Event::assertDispatched(PageUpdated::class, fn (PageUpdated $event): bool => $event->page->is($page)
        && $event->from->value === '/oud'
        && $event->to->value === '/nieuw');
});
