<?php

declare(strict_types=1);

use Made\CmsPackage\Content\Actions\ListParentOptions;
use Made\CmsPackage\Content\Data\PageNode;
use Made\CmsPackage\Content\Models\Page;

it('offers every page but the home page as the parent of a new page, because under the home page is the top', function (): void {
    Page::factory()->atPosition(0)->at('/')->create();
    $about = Page::factory()->atPosition(1)->at('/over-ons')->create();
    $team = Page::factory()->under($about)->at('/over-ons/team')->create();

    expect(array_map(fn (PageNode $node): int => $node->id, ListParentOptions::run()->flatten()))->toBe([$about->id, $team->id]);
});

it('never offers a page or a page under it as its own parent', function (): void {
    $about = Page::factory()->atPosition(0)->at('/over-ons')->create();
    $contact = Page::factory()->atPosition(1)->at('/contact')->create();
    Page::factory()->under($about)->at('/over-ons/team')->create();

    expect(array_map(fn (PageNode $node): int => $node->id, ListParentOptions::run($about)->flatten()))->toBe([$contact->id]);
});

it('offers the pages under the home page in its place', function (): void {
    $home = Page::factory()->atPosition(0)->at('/')->create();
    $news = Page::factory()->under($home)->at('/nieuws')->create();

    $options = ListParentOptions::run()->flatten();

    expect(array_map(fn (PageNode $node): array => [$node->id, $node->depth], $options))->toBe([[$news->id, 0]]);
});

it('keeps the home page for a page that lies under it already', function (): void {
    $home = Page::factory()->atPosition(0)->at('/')->create();
    $news = Page::factory()->under($home)->at('/nieuws')->create();

    expect(array_map(fn (PageNode $node): int => $node->id, ListParentOptions::run($news)->flatten()))->toBe([$home->id]);
});
