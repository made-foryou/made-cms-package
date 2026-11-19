<?php

declare(strict_types=1);

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Made\CmsPackage\Content\Actions\CheckPageParent;
use Made\CmsPackage\Content\Exceptions\CircularPageMove;
use Made\CmsPackage\Content\Exceptions\HomePageProtected;
use Made\CmsPackage\Content\Exceptions\ParentPageDeleted;
use Made\CmsPackage\Content\Models\Page;

it('lets a page go under a page beside it or at the top', function (): void {
    $about = Page::factory()->at('/over-ons')->create(['slug' => 'over-ons']);
    $team = Page::factory()->under($about)->at('/over-ons/team')->create(['slug' => 'team']);
    $contact = Page::factory()->at('/contact')->create(['slug' => 'contact']);

    DB::transaction(static function () use ($contact, $team): void {
        CheckPageParent::run($contact, $team);
        CheckPageParent::run($team, null);
    });
})->throwsNoExceptions();

it('refuses the page itself and the pages under it', function (string $target): void {
    $about = Page::factory()->at('/over-ons')->create(['slug' => 'over-ons']);
    $team = Page::factory()->under($about)->at('/over-ons/team')->create(['slug' => 'team']);
    $youth = Page::factory()->under($team)->at('/over-ons/team/jeugd')->create(['slug' => 'jeugd']);
    $parent = ['itself' => $about, 'child' => $team, 'grandchild' => $youth][$target];

    expect(fn () => DB::transaction(static fn () => CheckPageParent::run($about, $parent)))->toThrow(CircularPageMove::class);
})->with(['itself', 'child', 'grandchild']);

it('refuses any parent for the home page', function (): void {
    $home = Page::factory()->at('/')->create(['slug' => 'home']);
    $about = Page::factory()->at('/over-ons')->create(['slug' => 'over-ons']);

    expect(fn () => DB::transaction(static fn () => CheckPageParent::run($home->load('route'), $about)))->toThrow(HomePageProtected::class);
});

it('reads the tree as it is now, not as the parent it is handed holds it', function (): void {
    $about = Page::factory()->at('/over-ons')->create(['slug' => 'over-ons']);
    $team = Page::factory()->at('/team')->create(['slug' => 'team']);
    $handed = Page::query()->findOrFail($team->id);
    // Another request hung the team under the page after the team was read.
    DB::table('cms_pages')->where('id', $team->id)->update(['parent_id' => $about->id]);

    expect(fn () => DB::transaction(static fn () => CheckPageParent::run($about, $handed)))->toThrow(CircularPageMove::class);
});

it('refuses a parent that was deleted after it was read', function (string $deleted): void {
    $about = Page::factory()->at('/over-ons')->create(['slug' => 'over-ons']);
    $team = Page::factory()->under($about)->at('/over-ons/team')->create(['slug' => 'team']);
    $contact = Page::factory()->at('/contact')->create(['slug' => 'contact']);
    $handed = Page::query()->findOrFail($team->id);
    DB::table('cms_pages')->where('id', ['parent' => $team->id, 'page above it' => $about->id][$deleted])->update(['deleted_at' => now()]);

    expect(fn () => DB::transaction(static fn () => CheckPageParent::run($contact, $handed)))->toThrow(ParentPageDeleted::class);
})->with(['parent', 'page above it']);

it('reads the parent and every page above it inside the transaction of its caller', function (): void {
    $about = Page::factory()->at('/over-ons')->create(['slug' => 'over-ons']);
    $team = Page::factory()->under($about)->at('/over-ons/team')->create(['slug' => 'team']);
    $contact = Page::factory()->at('/contact')->create(['slug' => 'contact']);
    $outside = DB::transactionLevel();
    $levels = [];

    DB::listen(static function (QueryExecuted $query) use (&$levels): void {
        if (str_starts_with($query->sql, 'select') && str_contains($query->sql, 'cms_pages')) {
            $levels[] = DB::transactionLevel();
        }
    });

    DB::transaction(static fn () => CheckPageParent::run($contact, $team));

    expect($levels)->toBe([$outside + 1, $outside + 1]);
});

it('stops at a page it saw before, so a loop already in the tree never runs for ever', function (): void {
    $a = Page::factory()->at('/a')->create(['slug' => 'a']);
    $b = Page::factory()->under($a)->at('/a/b')->create(['slug' => 'b']);
    DB::table('cms_pages')->where('id', $a->id)->update(['parent_id' => $b->id]);
    $page = Page::factory()->at('/los')->create(['slug' => 'los']);

    DB::transaction(static fn () => CheckPageParent::run($page, $b));
})->throwsNoExceptions();
