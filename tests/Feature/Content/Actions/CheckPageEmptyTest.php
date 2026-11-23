<?php

declare(strict_types=1);

use Made\CmsPackage\Content\Actions\CheckPageEmpty;
use Made\CmsPackage\Content\Models\Page;

it('calls a page without strips in either version empty', function (): void {
    expect(CheckPageEmpty::run(Page::factory()->create()))->toBeTrue()
        ->and(CheckPageEmpty::run(Page::factory()->withDraft([])->create()))->toBeTrue();
});

it('does not call a page with a strip in either version empty', function (Closure $page): void {
    expect(CheckPageEmpty::run($page()))->toBeFalse();
})->with([
    'in the draft' => [static fn (): Page => Page::factory()->withDraft([storedStrip()])->create()],
    'published' => [static fn (): Page => Page::factory()->published()->withStrips([storedStrip()])->create()],
]);
