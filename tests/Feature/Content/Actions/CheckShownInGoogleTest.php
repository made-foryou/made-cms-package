<?php

declare(strict_types=1);

use Made\CmsPackage\Content\Actions\CheckShownInGoogle;
use Made\CmsPackage\Content\Models\Page;

it('says Google shows a published page it may show', function (): void {
    expect(CheckShownInGoogle::run(Page::factory()->published()->create()))->toBeTrue();
});

it('says Google does not show a page that is not out there for it', function (Closure $page): void {
    expect(CheckShownInGoogle::run($page()))->toBeFalse();
})->with([
    'never published' => [static fn (): Page => Page::factory()->create()],
    'noindex' => [static fn (): Page => Page::factory()->published()->create(['indexable' => false])],
    'hidden' => [static fn (): Page => Page::factory()->published()->hidden()->create()],
]);
