<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Event;
use Made\CmsPackage\Content\Actions\MovePage;
use Made\CmsPackage\Content\Events\PageMoved;
use Made\CmsPackage\Content\Exceptions\CircularPageMove;
use Made\CmsPackage\Content\Exceptions\HomePageProtected;
use Made\CmsPackage\Content\Exceptions\InvalidPagePosition;
use Made\CmsPackage\Content\Exceptions\PagePathTaken;
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

it('leaves every address as it was when rewriting fails', function (): void {
    $about = Page::factory()->atPosition(0)->at('/over-ons')->create(['slug' => 'over-ons']);
    $team = Page::factory()->under($about)->at('/over-ons/team')->create(['slug' => 'team']);
    $youth = Page::factory()->under($team)->at('/over-ons/team/jeugd')->create(['slug' => 'jeugd']);
    // An address made by hand, outside the tree, where the page under the moving page would go.
    Page::factory()->atPosition(1)->at('/team/jeugd')->create(['slug' => 'los']);

    expect(fn (): Page => MovePage::run($team, null))->toThrow(QueryException::class);

    expect($team->refresh()->parent_id)->toBe($about->id)
        ->and(Route::query()->where('routable_id', $team->id)->value('path'))->toBe('/over-ons/team')
        ->and($youth->refresh()->route?->path)->toBe('/over-ons/team/jeugd');
});
