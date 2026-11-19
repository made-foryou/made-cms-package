<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Made\CmsPackage\Content\Actions\DeletePage;
use Made\CmsPackage\Content\Actions\MovePage;
use Made\CmsPackage\Content\Actions\RestorePage;
use Made\CmsPackage\Content\Actions\UpdatePage;
use Made\CmsPackage\Content\Data\PageChanges;
use Made\CmsPackage\Content\Events\PageRestored;
use Made\CmsPackage\Content\Exceptions\PageNotDeleted;
use Made\CmsPackage\Content\Exceptions\PagePathTaken;
use Made\CmsPackage\Content\Exceptions\PagePathTooLong;
use Made\CmsPackage\Content\Exceptions\ParentPageDeleted;
use Made\CmsPackage\Content\Models\Page;

function deletedPage(Page $page): Page
{
    DeletePage::run($page);

    return Page::query()->withTrashed()->findOrFail($page->id);
}

it('restores a deleted page at the address of its parent and its slug', function (): void {
    $about = Page::factory()->at('/over-ons')->create(['slug' => 'over-ons']);
    $team = deletedPage(Page::factory()->under($about)->at('/over-ons/team')->create(['slug' => 'team']));

    $restored = RestorePage::run($team);

    expect($restored->trashed())->toBeFalse()
        ->and($restored->route?->path)->toBe('/over-ons/team')
        ->and(Page::query()->find($team->id))->not->toBeNull();
});

it('restores a page at the top', function (): void {
    $page = deletedPage(Page::factory()->at('/over-ons')->create(['slug' => 'over-ons']));

    expect(RestorePage::run($page)->route?->path)->toBe('/over-ons');
});

it('puts the restored page last among its siblings', function (): void {
    $page = deletedPage(Page::factory()->atPosition(0)->at('/a')->create(['slug' => 'a']));
    Page::factory()->atPosition(0)->at('/b')->create(['slug' => 'b']);
    Page::factory()->atPosition(1)->at('/c')->create(['slug' => 'c']);

    expect(RestorePage::run($page)->position)->toBe(2);
});

it('follows a parent that changed its address meanwhile', function (): void {
    $about = Page::factory()->at('/over-ons')->create(['slug' => 'over-ons']);
    $team = deletedPage(Page::factory()->under($about)->at('/over-ons/team')->create(['slug' => 'team']));

    UpdatePage::run($about, new PageChanges(title: 'Vereniging', slug: 'vereniging', parentId: null, inMenu: true));

    expect(RestorePage::run($team)->route?->path)->toBe('/vereniging/team');
});

it('refuses a page that is not deleted', function (): void {
    $page = Page::factory()->at('/over-ons')->create();

    expect(fn (): Page => RestorePage::run($page))->toThrow(PageNotDeleted::class);
});

it('refuses a page whose parent is deleted', function (): void {
    $about = Page::factory()->at('/over-ons')->create(['slug' => 'over-ons']);
    $team = deletedPage(Page::factory()->under($about)->at('/over-ons/team')->create(['slug' => 'team']));
    DeletePage::run($about);

    expect(fn (): Page => RestorePage::run($team))->toThrow(ParentPageDeleted::class);

    expect(Page::query()->withTrashed()->findOrFail($team->id)->trashed())->toBeTrue();
});

it('refuses an address another page took meanwhile', function (): void {
    $page = deletedPage(Page::factory()->at('/over-ons')->create(['slug' => 'over-ons']));
    Page::factory()->at('/over-ons')->create(['slug' => 'over-ons']);

    expect(fn (): Page => RestorePage::run($page))->toThrow(PagePathTaken::class);

    expect(Page::query()->withTrashed()->findOrFail($page->id)->trashed())->toBeTrue();
});

it('leaves the pages under it deleted', function (): void {
    $about = Page::factory()->at('/over-ons')->create(['slug' => 'over-ons']);
    $team = deletedPage(Page::factory()->under($about)->at('/over-ons/team')->create(['slug' => 'team']));
    $about = deletedPage($about);

    RestorePage::run($about);

    expect(Page::query()->withTrashed()->findOrFail($team->id)->trashed())->toBeTrue();
});

it('dispatches an event with the page and its address', function (): void {
    Event::fake([PageRestored::class]);
    $page = deletedPage(Page::factory()->at('/over-ons')->create(['slug' => 'over-ons']));

    RestorePage::run($page);

    Event::assertDispatched(PageRestored::class, fn (PageRestored $event): bool => $event->page->is($page) && $event->path->value === '/over-ons');
});

it('refuses an address that got too long meanwhile and leaves the page deleted', function (): void {
    $above = Page::factory()->atPosition(0)->at('/'.str_repeat('b', 120))->create(['slug' => str_repeat('b', 120)]);
    $parent = Page::factory()->atPosition(1)->at('/'.str_repeat('a', 120))->create(['slug' => str_repeat('a', 120)]);
    $page = deletedPage(Page::factory()->under($parent)->at('/'.str_repeat('a', 120).'/'.str_repeat('d', 13))->create(['slug' => str_repeat('d', 13)]));

    // The parent moves to 242 characters, so the page would be 256.
    MovePage::run($parent, $above->id);

    expect(fn (): Page => RestorePage::run($page))->toThrow(PagePathTooLong::class);

    expect(Page::query()->withTrashed()->findOrFail($page->id)->trashed())->toBeTrue();
});

it('refuses an address another request stores at the same moment, as a taken address', function (): void {
    Event::fake([PageRestored::class]);
    $page = deletedPage(Page::factory()->at('/over-ons')->create(['slug' => 'over-ons']));
    claimAddressFirst();

    expect(fn (): Page => RestorePage::run($page))->toThrow(PagePathTaken::class, 'There is already a page at [/over-ons].');

    expect(Page::query()->withTrashed()->findOrFail($page->id)->trashed())->toBeTrue();
    Event::assertNotDispatched(PageRestored::class);
});

it('refuses a page another request restored at the same moment', function (): void {
    Event::fake([PageRestored::class]);
    $page = deletedPage(Page::factory()->at('/over-ons')->create(['slug' => 'over-ons']));
    // The other request restores the page after this one read it, and commits before this one starts writing.
    $competing = true;
    DB::connection()->beforeStartingTransaction(static function () use (&$competing, $page): void {
        if ($competing) {
            $competing = false;
            DB::table('cms_pages')->where('id', $page->id)->update(['deleted_at' => null]);
            DB::table('cms_routes')->insert(['path' => '/over-ons', 'routable_type' => 'cms_page', 'routable_id' => $page->id, 'created_at' => now(), 'updated_at' => now()]);
        }
    });

    expect(fn (): Page => RestorePage::run($page))->toThrow(PageNotDeleted::class);

    expect(Page::query()->with('route')->findOrFail($page->id)->route?->path)->toBe('/over-ons');
    Event::assertNotDispatched(PageRestored::class);
});
