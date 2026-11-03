<?php

declare(strict_types=1);

use Made\CmsPackage\Content\Actions\ListParentOptions;
use Made\CmsPackage\Content\Data\PageNode;
use Made\CmsPackage\Content\Models\Page;

it('offers every page as the parent of a new page', function (): void {
    $home = Page::factory()->atPosition(0)->at('/')->create();
    $about = Page::factory()->atPosition(1)->at('/over-ons')->create();

    expect(array_map(fn (PageNode $node): int => $node->id, ListParentOptions::run()->flatten()))->toBe([$home->id, $about->id]);
});

it('never offers a page or a page under it as its own parent', function (): void {
    $home = Page::factory()->atPosition(0)->at('/')->create();
    $about = Page::factory()->atPosition(1)->at('/over-ons')->create();
    Page::factory()->under($about)->at('/over-ons/team')->create();

    expect(array_map(fn (PageNode $node): int => $node->id, ListParentOptions::run($about)->flatten()))->toBe([$home->id]);
});
