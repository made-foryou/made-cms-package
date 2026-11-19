<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Made\CmsPackage\Content\Actions\MovePage;
use Made\CmsPackage\Content\Events\PageMoved;
use Made\CmsPackage\Content\Exceptions\CircularPageMove;
use Made\CmsPackage\Content\Exceptions\HomePageProtected;
use Made\CmsPackage\Content\Exceptions\InvalidPagePosition;
use Made\CmsPackage\Content\Exceptions\PagePathTaken;
use Made\CmsPackage\Content\Exceptions\PagePathTooLong;
use Made\CmsPackage\Content\Exceptions\ParentPageDeleted;
use Made\CmsPackage\Content\Models\Page;
use Made\CmsPackage\Content\Models\Route;

/**
 * @return list<int>
 */
function siblingIds(?Page $parent): array
{
    return Page::query()->childrenOf($parent)->inMenuOrder()->pluck('id')->all();
}

/**
 * @return list<int>
 */
function siblingPositions(?Page $parent): array
{
    return Page::query()->childrenOf($parent)->inMenuOrder()->pluck('position')->all();
}

it('moves a page under another parent and rewrites its address', function (): void {
    $about = Page::factory()->atPosition(0)->at('/over-ons')->create(['slug' => 'over-ons']);
    $club = Page::factory()->atPosition(1)->at('/vereniging')->create(['slug' => 'vereniging']);
    $team = Page::factory()->under($about)->at('/over-ons/team')->create(['slug' => 'team']);

    MovePage::run($team, $club->id);

    expect($team->refresh()->parent_id)->toBe($club->id)
        ->and($team->route?->path)->toBe('/vereniging/team');
});

it('rewrites the addresses of every page under it', function (): void {
    $about = Page::factory()->at('/over-ons')->create(['slug' => 'over-ons']);
    $team = Page::factory()->under($about)->at('/over-ons/team')->create(['slug' => 'team']);
    $youth = Page::factory()->under($team)->at('/over-ons/team/jeugd')->create(['slug' => 'jeugd']);
    $young = Page::factory()->under($youth)->at('/over-ons/team/jeugd/mini')->create(['slug' => 'mini']);

    MovePage::run($team, null);

    expect($team->refresh()->route?->path)->toBe('/team')
        ->and($youth->refresh()->route?->path)->toBe('/team/jeugd')
        ->and($young->refresh()->route?->path)->toBe('/team/jeugd/mini');
});

it('moves a page under the home page to an address at the top', function (): void {
    $home = Page::factory()->atPosition(0)->at('/')->create(['slug' => 'home']);
    $about = Page::factory()->atPosition(1)->at('/over-ons')->create(['slug' => 'over-ons']);
    $team = Page::factory()->under($about)->at('/over-ons/team')->create(['slug' => 'team']);

    MovePage::run($team, $home->id);

    expect($team->refresh()->route?->path)->toBe('/team');
});

it('puts the page last when no place is given', function (): void {
    $first = Page::factory()->atPosition(0)->at('/a')->create(['slug' => 'a']);
    $second = Page::factory()->atPosition(1)->at('/b')->create(['slug' => 'b']);
    $about = Page::factory()->atPosition(2)->at('/over-ons')->create(['slug' => 'over-ons']);
    $team = Page::factory()->under($about)->at('/over-ons/team')->create(['slug' => 'team']);

    MovePage::run($team, null);

    expect(siblingIds(null))->toBe([$first->id, $second->id, $about->id, $team->id])
        ->and(siblingPositions(null))->toBe([0, 1, 2, 3]);
});

it('inserts the page at a place and shifts the pages after it', function (): void {
    $first = Page::factory()->atPosition(0)->at('/a')->create(['slug' => 'a']);
    $second = Page::factory()->atPosition(1)->at('/b')->create(['slug' => 'b']);
    $about = Page::factory()->atPosition(2)->at('/over-ons')->create(['slug' => 'over-ons']);
    $team = Page::factory()->under($about)->at('/over-ons/team')->create(['slug' => 'team']);

    MovePage::run($team, null, 1);

    expect(siblingIds(null))->toBe([$first->id, $team->id, $second->id, $about->id])
        ->and(siblingPositions(null))->toBe([0, 1, 2, 3]);
});

it('closes the gap among the pages it leaves', function (): void {
    $about = Page::factory()->atPosition(0)->at('/over-ons')->create(['slug' => 'over-ons']);
    $club = Page::factory()->atPosition(1)->at('/vereniging')->create(['slug' => 'vereniging']);
    Page::factory()->under($about)->atPosition(0)->at('/over-ons/a')->create(['slug' => 'a']);
    $moving = Page::factory()->under($about)->atPosition(1)->at('/over-ons/b')->create(['slug' => 'b']);
    Page::factory()->under($about)->atPosition(2)->at('/over-ons/c')->create(['slug' => 'c']);

    MovePage::run($moving, $club->id);

    expect(siblingPositions($about))->toBe([0, 1]);
});

it('reorders a page among its siblings without changing its address', function (): void {
    $first = Page::factory()->atPosition(0)->at('/a')->create(['slug' => 'a']);
    $second = Page::factory()->atPosition(1)->at('/b')->create(['slug' => 'b']);
    $third = Page::factory()->atPosition(2)->at('/c')->create(['slug' => 'c']);

    MovePage::run($third, null, 0);

    expect(siblingIds(null))->toBe([$third->id, $first->id, $second->id])
        ->and(siblingPositions(null))->toBe([0, 1, 2])
        ->and($third->refresh()->route?->path)->toBe('/c');
});

it('does nothing when the page already is at that place', function (?int $position): void {
    Event::fake([PageMoved::class]);
    Page::factory()->atPosition(0)->at('/a')->create(['slug' => 'a']);
    $last = Page::factory()->atPosition(1)->at('/b')->create(['slug' => 'b', 'updated_at' => now()->subDay()]);
    $before = $last->updated_at;

    MovePage::run($last, null, $position);

    expect($last->refresh()->updated_at?->equalTo($before))->toBeTrue();
    Event::assertNotDispatched(PageMoved::class);
})->with(['the same place' => [1], 'last again' => [null]]);

it('refuses a place outside the siblings', function (int $position): void {
    $first = Page::factory()->atPosition(0)->at('/a')->create(['slug' => 'a']);
    Page::factory()->atPosition(1)->at('/b')->create(['slug' => 'b']);

    expect(fn (): Page => MovePage::run($first, null, $position))->toThrow(InvalidPagePosition::class);
})->with([-1, 2]);

it('refuses to move a page under itself or a page under it', function (string $target): void {
    $about = Page::factory()->at('/over-ons')->create(['slug' => 'over-ons']);
    $team = Page::factory()->under($about)->at('/over-ons/team')->create(['slug' => 'team']);
    $youth = Page::factory()->under($team)->at('/over-ons/team/jeugd')->create(['slug' => 'jeugd']);
    $parent = ['itself' => $about, 'child' => $team, 'grandchild' => $youth][$target];

    expect(fn (): Page => MovePage::run($about, $parent->id))->toThrow(CircularPageMove::class);

    expect($about->refresh()->parent_id)->toBeNull();
})->with(['itself', 'child', 'grandchild']);

it('refuses an address that is taken at the new place and changes nothing', function (): void {
    Event::fake([PageMoved::class]);
    $about = Page::factory()->atPosition(0)->at('/over-ons')->create(['slug' => 'over-ons']);
    $team = Page::factory()->under($about)->at('/over-ons/team')->create(['slug' => 'team']);
    Page::factory()->atPosition(1)->at('/team')->create(['slug' => 'team']);

    expect(fn (): Page => MovePage::run($team, null))->toThrow(PagePathTaken::class);

    expect($team->refresh()->parent_id)->toBe($about->id)
        ->and($team->route?->path)->toBe('/over-ons/team');
    Event::assertNotDispatched(PageMoved::class);
});

it('refuses a deleted parent', function (): void {
    $page = Page::factory()->at('/a')->create(['slug' => 'a']);
    $deleted = Page::factory()->trashed()->create();

    expect(fn (): Page => MovePage::run($page, $deleted->id))->toThrow(ParentPageDeleted::class);
});

it('keeps the home page at the top', function (): void {
    $home = Page::factory()->atPosition(0)->at('/')->create(['slug' => 'home']);
    $about = Page::factory()->atPosition(1)->at('/over-ons')->create(['slug' => 'over-ons']);

    expect(fn (): Page => MovePage::run($home, $about->id))->toThrow(HomePageProtected::class);
});

it('reorders the home page among the pages at the top', function (): void {
    $home = Page::factory()->atPosition(0)->at('/')->create(['slug' => 'home']);
    $about = Page::factory()->atPosition(1)->at('/over-ons')->create(['slug' => 'over-ons']);

    MovePage::run($home, null, 1);

    expect(siblingIds(null))->toBe([$about->id, $home->id])
        ->and($home->refresh()->route?->path)->toBe('/');
});

it('leaves siblings whose place stays the same unedited', function (): void {
    $first = Page::factory()->atPosition(0)->at('/a')->create(['slug' => 'a', 'updated_at' => now()->subDay()]);
    $before = $first->updated_at;
    Page::factory()->atPosition(1)->at('/b')->create(['slug' => 'b']);
    $third = Page::factory()->atPosition(2)->at('/c')->create(['slug' => 'c']);

    MovePage::run($third, null, 1);

    expect($first->refresh()->updated_at?->equalTo($before))->toBeTrue();
});

it('dispatches an event with the old and the new address', function (): void {
    Event::fake([PageMoved::class]);
    $about = Page::factory()->atPosition(0)->at('/over-ons')->create(['slug' => 'over-ons']);
    $team = Page::factory()->under($about)->at('/over-ons/team')->create(['slug' => 'team']);

    MovePage::run($team, null);

    Event::assertDispatched(PageMoved::class, fn (PageMoved $event): bool => $event->page->is($team)
        && $event->from->value === '/over-ons/team'
        && $event->to->value === '/team');
});

it('dispatches an event with the same address for a new place among the siblings', function (): void {
    Event::fake([PageMoved::class]);
    Page::factory()->atPosition(0)->at('/a')->create(['slug' => 'a']);
    $second = Page::factory()->atPosition(1)->at('/b')->create(['slug' => 'b']);

    MovePage::run($second, null, 0);

    Event::assertDispatched(PageMoved::class, fn (PageMoved $event): bool => $event->from->value === '/b' && $event->to->value === '/b');
});

it('refuses as a taken address when a page under it would land on an address made by hand, and leaves every address as it was', function (): void {
    $about = Page::factory()->atPosition(0)->at('/over-ons')->create(['slug' => 'over-ons']);
    $team = Page::factory()->under($about)->at('/over-ons/team')->create(['slug' => 'team']);
    $youth = Page::factory()->under($team)->at('/over-ons/team/jeugd')->create(['slug' => 'jeugd']);
    // An address made by hand, outside the tree, where the page under the moving page would go.
    Page::factory()->atPosition(1)->at('/team/jeugd')->create(['slug' => 'los']);

    expect(fn (): Page => MovePage::run($team, null))->toThrow(PagePathTaken::class);

    expect($team->refresh()->parent_id)->toBe($about->id)
        ->and(Route::query()->where('routable_id', $team->id)->value('path'))->toBe('/over-ons/team')
        ->and($youth->refresh()->route?->path)->toBe('/over-ons/team/jeugd');
});

/**
 * A page "/m" with a page under it of 123 characters, and a parent of 121 + 1 + $length characters to move it under.
 *
 * @return array{Page, Page, Page}
 */
function deepMove(int $length): array
{
    $deep = Page::factory()->atPosition(0)->at('/'.str_repeat('t', 120))->create(['slug' => str_repeat('t', 120)]);
    $parent = Page::factory()->under($deep)->at('/'.str_repeat('t', 120).'/'.str_repeat('u', $length))->create(['slug' => str_repeat('u', $length)]);
    $page = Page::factory()->atPosition(1)->at('/m')->create(['slug' => 'm']);
    $child = Page::factory()->under($page)->at('/m/'.str_repeat('c', 120))->create(['slug' => str_repeat('c', 120)]);

    return [$parent, $page, $child];
}

it('moves a page where the address of a page under it gets the longest length', function (): void {
    [$parent, $page, $child] = deepMove(10);

    MovePage::run($page, $parent->id);

    expect(mb_strlen((string) $child->refresh()->route?->path))->toBe(255);
});

it('refuses a place where the address of a page under it gets too long and moves nothing', function (): void {
    Event::fake([PageMoved::class]);
    [$parent, $page, $child] = deepMove(11);

    expect(fn (): Page => MovePage::run($page, $parent->id))->toThrow(PagePathTooLong::class);

    $stored = Page::query()->with('route')->findOrFail($page->id);

    expect($stored->parent_id)->toBeNull()
        ->and($stored->position)->toBe(1)
        ->and($stored->route?->path)->toBe('/m')
        ->and($child->refresh()->route?->path)->toBe('/m/'.str_repeat('c', 120));
    Event::assertNotDispatched(PageMoved::class);
});

it('refuses an address another request stores at the same moment, as a taken address', function (): void {
    Event::fake([PageMoved::class]);
    $about = Page::factory()->atPosition(0)->at('/over-ons')->create(['slug' => 'over-ons']);
    $team = Page::factory()->atPosition(1)->at('/team')->create(['slug' => 'team']);
    claimAddressFirst();

    expect(fn (): Page => MovePage::run($team, $about->id))
        ->toThrow(PagePathTaken::class, 'There is already a page at [/over-ons/team].');

    $stored = Page::query()->with('route')->findOrFail($team->id);

    expect($stored->parent_id)->toBeNull()
        ->and($stored->position)->toBe(1)
        ->and($stored->route?->path)->toBe('/team');
    Event::assertNotDispatched(PageMoved::class);
});

it('refuses a parent that went under the page at the same moment', function (): void {
    Event::fake([PageMoved::class]);
    $a = Page::factory()->atPosition(0)->at('/a')->create(['slug' => 'a']);
    $b = Page::factory()->atPosition(1)->at('/b')->create(['slug' => 'b']);
    // Another request hangs B under A after this one found B, and commits before this one starts writing.
    $competing = true;
    DB::connection()->beforeStartingTransaction(static function () use (&$competing, $a, $b): void {
        if ($competing) {
            $competing = false;
            DB::table('cms_pages')->where('id', $b->id)->update(['parent_id' => $a->id]);
        }
    });

    expect(fn (): Page => MovePage::run($a, $b->id))->toThrow(CircularPageMove::class);

    expect(Page::query()->findOrFail($a->id)->parent_id)->toBeNull();
    Event::assertNotDispatched(PageMoved::class);
});

it('refuses a parent deleted at the same moment', function (): void {
    $a = Page::factory()->atPosition(0)->at('/a')->create(['slug' => 'a']);
    $b = Page::factory()->atPosition(1)->at('/b')->create(['slug' => 'b']);
    $competing = true;
    DB::connection()->beforeStartingTransaction(static function () use (&$competing, $b): void {
        if ($competing) {
            $competing = false;
            DB::table('cms_pages')->where('id', $b->id)->update(['deleted_at' => now()]);
        }
    });

    expect(fn (): Page => MovePage::run($a, $b->id))->toThrow(ParentPageDeleted::class);

    expect(Page::query()->findOrFail($a->id)->parent_id)->toBeNull();
});
