<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Made\CmsPackage\Content\Actions\CreatePage;
use Made\CmsPackage\Content\Data\NewPage;
use Made\CmsPackage\Content\Enums\PageStatus;
use Made\CmsPackage\Content\Events\PageCreated;
use Made\CmsPackage\Content\Exceptions\InvalidPageSlug;
use Made\CmsPackage\Content\Exceptions\PagePathTaken;
use Made\CmsPackage\Content\Exceptions\ParentPageDeleted;
use Made\CmsPackage\Content\Exceptions\ParentPageNotFound;
use Made\CmsPackage\Content\Models\Page;
use Made\CmsPackage\Content\Models\Route;

it('creates an empty draft at the top with its address', function (): void {
    $page = CreatePage::run(new NewPage(title: ' Jeugd ', slug: null, parentId: null));

    $stored = Page::query()->with('route')->findOrFail($page->id);

    expect($stored->title)->toBe('Jeugd')
        ->and($stored->slug)->toBe('jeugd')
        ->and($stored->status)->toBe(PageStatus::Draft)
        ->and($stored->strips)->toBe([])
        ->and($stored->draft_strips)->toBeNull()
        ->and($stored->parent_id)->toBeNull()
        ->and($stored->in_menu)->toBeTrue()
        ->and($stored->route?->path)->toBe('/jeugd');
});

it('creates a page under a parent at the address of the parent and its slug', function (): void {
    $about = Page::factory()->at('/over-ons')->create();

    $page = CreatePage::run(new NewPage(title: 'Team', slug: null, parentId: $about->id));

    expect($page->parent_id)->toBe($about->id)
        ->and($page->route?->path)->toBe('/over-ons/team');
});

it('creates a page under the home page at an address at the top', function (): void {
    $home = Page::factory()->at('/')->create();

    expect(CreatePage::run(new NewPage(title: 'Team', slug: null, parentId: $home->id))->route?->path)->toBe('/team');
});

it('derives the slug from the title when none is typed', function (?string $slug): void {
    expect(CreatePage::run(new NewPage(title: 'Over ons', slug: $slug, parentId: null))->slug)->toBe('over-ons');
})->with(['none' => [null], 'blank' => ['  ']]);

it('normalises a typed slug', function (): void {
    $page = CreatePage::run(new NewPage(title: 'Team', slug: 'Ons Team!', parentId: null));

    expect($page->slug)->toBe('ons-team')
        ->and($page->route?->path)->toBe('/ons-team');
});

it('refuses a title that leaves no slug', function (): void {
    expect(fn (): Page => CreatePage::run(new NewPage(title: '???', slug: null, parentId: null)))->toThrow(InvalidPageSlug::class);

    expect(Page::query()->count())->toBe(0);
});

it('puts the page last among its siblings', function (): void {
    $parent = Page::factory()->at('/over-ons')->create();
    Page::factory()->under($parent)->atPosition(0)->create();
    Page::factory()->under($parent)->atPosition(5)->create();

    expect(CreatePage::run(new NewPage(title: 'Team', slug: null, parentId: $parent->id))->position)->toBe(6);
});

it('starts at the first place under a parent without children', function (): void {
    $parent = Page::factory()->at('/over-ons')->create();

    expect(CreatePage::run(new NewPage(title: 'Team', slug: null, parentId: $parent->id))->position)->toBe(0);
});

it('refuses an address that is taken', function (): void {
    Event::fake([PageCreated::class]);
    $parent = Page::factory()->at('/over-ons')->create();
    Page::factory()->under($parent)->at('/over-ons/team')->create(['slug' => 'team']);

    expect(fn (): Page => CreatePage::run(new NewPage(title: 'Team', slug: null, parentId: $parent->id)))
        ->toThrow(PagePathTaken::class, 'There is already a page at [/over-ons/team].');

    expect(Page::query()->count())->toBe(2)
        ->and(Route::query()->count())->toBe(2);
    Event::assertNotDispatched(PageCreated::class);
});

it('refuses an address at the top that a child of the home page holds', function (): void {
    $home = Page::factory()->at('/')->create();
    CreatePage::run(new NewPage(title: 'Team', slug: null, parentId: $home->id));

    expect(fn (): Page => CreatePage::run(new NewPage(title: 'Team', slug: null, parentId: null)))->toThrow(PagePathTaken::class);
});

it('lets pages under different parents share a slug', function (): void {
    $about = Page::factory()->at('/over-ons')->create();
    $club = Page::factory()->at('/vereniging')->create();

    CreatePage::run(new NewPage(title: 'Team', slug: null, parentId: $about->id));
    $second = CreatePage::run(new NewPage(title: 'Team', slug: null, parentId: $club->id));

    expect($second->route?->path)->toBe('/vereniging/team');
});

it('creates a page under a draft parent', function (): void {
    $parent = Page::factory()->at('/concept')->create();

    expect(CreatePage::run(new NewPage(title: 'Onder', slug: null, parentId: $parent->id))->route?->path)->toBe('/concept/onder');
});

it('stores a page outside the menu', function (): void {
    $page = CreatePage::run(new NewPage(title: 'Bedankt', slug: null, parentId: null, inMenu: false));

    expect($page->refresh()->in_menu)->toBeFalse();
});

it('refuses a parent that does not exist', function (): void {
    expect(fn (): Page => CreatePage::run(new NewPage(title: 'Team', slug: null, parentId: missingId())))->toThrow(ParentPageNotFound::class);
});

it('refuses a deleted parent', function (): void {
    $parent = Page::factory()->trashed()->create();

    expect(fn (): Page => CreatePage::run(new NewPage(title: 'Team', slug: null, parentId: $parent->id)))->toThrow(ParentPageDeleted::class);
});

it('dispatches an event with the new page', function (): void {
    Event::fake([PageCreated::class]);

    $page = CreatePage::run(new NewPage(title: 'Jeugd', slug: null, parentId: null));

    Event::assertDispatched(PageCreated::class, fn (PageCreated $event): bool => $event->page->is($page));
});
