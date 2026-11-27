<?php

declare(strict_types=1);

use Made\CmsPackage\Content\Actions\PrunePageVersions;
use Made\CmsPackage\Content\Models\Page;
use Made\CmsPackage\Content\Models\PageVersion;

/**
 * Gives the page versions with these numbers.
 *
 * @param  list<int>  $numbers
 */
function versionsOf(Page $page, array $numbers): void
{
    foreach ($numbers as $number) {
        PageVersion::factory()->for($page)->number($number)->create();
    }
}

/**
 * @return list<int>
 */
function keptNumbers(Page $page): array
{
    return array_values(PageVersion::query()->ofPage($page)->newestFirst()->pluck('number')->all());
}

it('keeps the newest versions of every page and says how many it removed', function (): void {
    $busy = Page::factory()->create();
    $quiet = Page::factory()->create();
    versionsOf($busy, [1, 2, 3, 4, 5]);
    versionsOf($quiet, [1, 2]);

    expect(PrunePageVersions::run(3))->toBe(2)
        ->and(keptNumbers($busy))->toBe([5, 4, 3])
        ->and(keptNumbers($quiet))->toBe([2, 1]);
});

it('keeps the configured number of versions', function (): void {
    config(['made-cms-package.pages.keep_versions' => 2]);
    $page = Page::factory()->create();
    versionsOf($page, [1, 2, 3]);

    expect(PrunePageVersions::run())->toBe(1)
        ->and(keptNumbers($page))->toBe([3, 2]);
});

it('always keeps the version visitors see', function (): void {
    config(['made-cms-package.pages.keep_versions' => 0]);
    $page = Page::factory()->create();
    versionsOf($page, [1, 2]);

    expect(PrunePageVersions::run())->toBe(1)
        ->and(keptNumbers($page))->toBe([2]);
});

it('counts the newest by number, also after a gap', function (): void {
    $page = Page::factory()->create();
    versionsOf($page, [2, 7, 9, 12]);

    PrunePageVersions::run(2);

    expect(keptNumbers($page))->toBe([12, 9]);
});

it('prunes the versions of a deleted page too', function (): void {
    $page = Page::factory()->deletedAt(now()->subDay())->create();
    versionsOf($page, [1, 2, 3]);

    expect(PrunePageVersions::run(1))->toBe(2)
        ->and(keptNumbers($page))->toBe([3]);
});
