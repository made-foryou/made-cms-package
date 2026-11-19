<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Made\CmsPackage\Content\Actions\CreatePage;
use Made\CmsPackage\Content\Actions\DeletePage;
use Made\CmsPackage\Content\Actions\FindPage;
use Made\CmsPackage\Content\Actions\FindPublishedPage;
use Made\CmsPackage\Content\Actions\ListPages;
use Made\CmsPackage\Content\Data\NewPage;
use Made\CmsPackage\Content\Data\PagePath;
use Made\CmsPackage\Content\Events\PageDeleted;
use Made\CmsPackage\Content\Exceptions\HomePageProtected;
use Made\CmsPackage\Content\Exceptions\PageHasChildren;
use Made\CmsPackage\Content\Models\Page;
use Made\CmsPackage\Content\Models\Route;
use Made\CmsPackage\Content\Strips\Fields\Link;

it('deletes the page and frees its address', function (): void {
    $page = Page::factory()->published()->at('/over-ons')->create();

    DeletePage::run($page);

    expect(Page::query()->find($page->id))->toBeNull()
        ->and(Page::query()->withTrashed()->find($page->id)?->trashed())->toBeTrue()
        ->and(Route::query()->count())->toBe(0);
});

it('lets a new page take the address of a deleted page', function (): void {
    DeletePage::run(Page::factory()->at('/over-ons')->create(['slug' => 'over-ons']));

    expect(CreatePage::run(new NewPage(title: 'Over ons', slug: null, parentId: null))->route?->path)->toBe('/over-ons');
});

it('hides a deleted page from visitors, editors and the tree', function (): void {
    $page = Page::factory()->published()->at('/over-ons')->create();

    DeletePage::run($page);

    expect(FindPage::run(PagePath::fromString('/over-ons')))->toBeNull()
        ->and(FindPublishedPage::run(PagePath::fromString('/over-ons')))->toBeNull()
        ->and(ListPages::run()->isEmpty())->toBeTrue();
});

it('refuses a page with pages under it', function (): void {
    Event::fake([PageDeleted::class]);
    $about = Page::factory()->at('/over-ons')->create();
    Page::factory()->under($about)->at('/over-ons/team')->create();

    expect(fn () => DeletePage::run($about))->toThrow(PageHasChildren::class);

    expect($about->refresh()->trashed())->toBeFalse()
        ->and($about->route?->path)->toBe('/over-ons');
    Event::assertNotDispatched(PageDeleted::class);
});

it('deletes a page whose children are deleted already', function (): void {
    $about = Page::factory()->at('/over-ons')->create();
    Page::factory()->under($about)->trashed()->create();

    DeletePage::run($about);

    expect($about->refresh()->trashed())->toBeTrue();
});

it('keeps the home page', function (): void {
    $home = Page::factory()->at('/')->create();

    expect(fn () => DeletePage::run($home))->toThrow(HomePageProtected::class);
});

it('closes the gap among the siblings', function (): void {
    Page::factory()->atPosition(0)->at('/a')->create();
    $middle = Page::factory()->atPosition(1)->at('/b')->create();
    Page::factory()->atPosition(2)->at('/c')->create();

    DeletePage::run($middle);

    expect(Page::query()->roots()->inMenuOrder()->pluck('position')->all())->toBe([0, 1]);
});

it('keeps the strips of the deleted page', function (): void {
    $strips = [storedStrip(id: 'a')];
    $page = Page::factory()->withStrips($strips)->at('/over-ons')->create();

    DeletePage::run($page);

    expect(Page::query()->withTrashed()->findOrFail($page->id)->strips)->toBeStoredAs($strips);
});

it('leaves a link to the deleted page without a url', function (): void {
    $page = Page::factory()->published()->at('/over-ons')->create();

    DeletePage::run($page);

    expect(Link::make('link')->render(['page' => $page->id]))->toBeNull();
});

it('dispatches an event with the page and the address it had', function (): void {
    Event::fake([PageDeleted::class]);
    $page = Page::factory()->at('/over-ons')->create();

    DeletePage::run($page);

    Event::assertDispatched(PageDeleted::class, fn (PageDeleted $event): bool => $event->page->is($page) && $event->path->value === '/over-ons');
});
