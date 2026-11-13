<?php

declare(strict_types=1);

use Made\CmsPackage\Content\Actions\ListMenuPages;
use Made\CmsPackage\Content\Actions\MovePage;
use Made\CmsPackage\Content\Data\PageNode;
use Made\CmsPackage\Content\Data\PageTree;
use Made\CmsPackage\Content\Models\Page;

/**
 * @return list<int>
 */
function menuIds(PageTree $tree): array
{
    return array_map(fn (PageNode $node): int => $node->id, $tree->flatten());
}

it('lists the published pages in the menu in menu order, with the pages under them', function (): void {
    $contact = Page::factory()->published()->atPosition(2)->at('/contact')->create();
    $home = Page::factory()->published()->atPosition(0)->at('/')->create();
    $about = Page::factory()->published()->atPosition(1)->at('/over-ons')->create();
    $history = Page::factory()->published()->under($about)->atPosition(1)->at('/over-ons/geschiedenis')->create();
    $team = Page::factory()->published()->under($about)->atPosition(0)->at('/over-ons/team')->create();

    $menu = ListMenuPages::run();

    expect(array_map(fn (PageNode $node): int => $node->id, $menu->roots))->toBe([$home->id, $about->id, $contact->id])
        ->and(menuIds($menu))->toBe([$home->id, $about->id, $team->id, $history->id, $contact->id]);
});

it('leaves out a draft and the published pages under it', function (): void {
    $home = Page::factory()->published()->atPosition(0)->at('/')->create();
    $blog = Page::factory()->atPosition(1)->at('/blog')->create();
    Page::factory()->published()->under($blog)->at('/blog/nieuws')->create();
    $contact = Page::factory()->published()->atPosition(2)->at('/contact')->create();

    expect(menuIds(ListMenuPages::run()))->toBe([$home->id, $contact->id]);
});

it('leaves out a page outside the menu and the pages under it', function (): void {
    $home = Page::factory()->published()->atPosition(0)->at('/')->create();
    $privacy = Page::factory()->published()->inMenu(false)->atPosition(1)->at('/privacy')->create();
    Page::factory()->published()->under($privacy)->at('/privacy/cookies')->create();

    expect(menuIds(ListMenuPages::run()))->toBe([$home->id]);
});

it('leaves out a deleted page', function (): void {
    $home = Page::factory()->published()->atPosition(0)->at('/')->create();
    Page::factory()->published()->trashed()->atPosition(1)->create();

    expect(menuIds(ListMenuPages::run()))->toBe([$home->id]);
});

it('follows the order of the pages screen', function (): void {
    $about = Page::factory()->published()->atPosition(0)->at('/over-ons')->create();
    $faq = Page::factory()->published()->atPosition(1)->at('/veelgestelde-vragen')->create();
    $blog = Page::factory()->published()->atPosition(2)->at('/blog')->create();

    MovePage::run($blog, parentId: null, position: 1);

    expect(menuIds(ListMenuPages::run()))->toBe([$about->id, $blog->id, $faq->id]);
});
