<?php

declare(strict_types=1);

use Made\CmsPackage\Content\Actions\PrunePages;
use Made\CmsPackage\Content\Models\Page;

it('removes pages deleted longer ago than the days for good', function (): void {
    $old = Page::factory()->deletedAt(now()->subDays(31))->create();

    expect(PrunePages::run(30))->toBe(1)
        ->and(Page::query()->withTrashed()->find($old->id))->toBeNull();
});

it('keeps pages deleted more recently and pages that are not deleted', function (): void {
    $recent = Page::factory()->deletedAt(now()->subDays(29))->create();
    $live = Page::factory()->create();

    expect(PrunePages::run(30))->toBe(0)
        ->and(Page::query()->withTrashed()->find($recent->id))->not->toBeNull()
        ->and(Page::query()->find($live->id))->not->toBeNull();
});

it('keeps deleted pages for the configured number of days', function (): void {
    config(['made-cms-package.pages.keep_deleted_days' => 5]);
    $page = Page::factory()->deletedAt(now()->subDays(6))->create();

    expect(PrunePages::run())->toBe(1)
        ->and(Page::query()->withTrashed()->find($page->id))->toBeNull();
});

it('removes a parent and its children deleted at the same moment', function (): void {
    $moment = now()->subDays(40);
    $parent = Page::factory()->deletedAt($moment)->create();
    $child = Page::factory()->under($parent)->deletedAt($moment)->create();
    Page::factory()->under($child)->deletedAt($moment)->create();

    expect(PrunePages::run(30))->toBe(3)
        ->and(Page::query()->withTrashed()->count())->toBe(0);
});
