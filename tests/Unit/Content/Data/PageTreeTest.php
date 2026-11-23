<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Made\CmsPackage\Content\Data\MenuPlace;
use Made\CmsPackage\Content\Data\PageNode;
use Made\CmsPackage\Content\Data\PageTree;
use Made\CmsPackage\Content\Enums\MenuPlaceReason;
use Made\CmsPackage\Content\Enums\PageStatus;

function sampleTree(): PageTree
{
    return new PageTree([
        pageNode(1, '/'),
        pageNode(2, '/over-ons', [
            pageNode(3, '/over-ons/team', [pageNode(4, '/over-ons/team/jeugd', parentId: 3, depth: 2)], parentId: 2, depth: 1),
            pageNode(5, '/over-ons/geschiedenis', inMenu: false, parentId: 2, depth: 1),
        ]),
        pageNode(6, '/contact', [pageNode(7, '/contact/route', parentId: 6, depth: 1)], inMenu: false),
    ]);
}

it('flattens the tree depth first in menu order', function (): void {
    expect(array_map(fn (PageNode $node): int => $node->id, sampleTree()->flatten()))->toBe([1, 2, 3, 4, 5, 6, 7]);
});

it('finds a node anywhere in the tree', function (): void {
    expect(sampleTree()->find(4)?->path?->value)->toBe('/over-ons/team/jeugd')
        ->and(sampleTree()->find(99))->toBeNull();
});

it('leaves out a page and its descendants', function (): void {
    expect(array_map(fn (PageNode $node): int => $node->id, sampleTree()->without(3)->flatten()))->toBe([1, 2, 5, 6, 7]);
});

it('keeps the tree when the page is not in it', function (): void {
    expect(array_map(fn (PageNode $node): int => $node->id, sampleTree()->without(99)->flatten()))->toBe([1, 2, 3, 4, 5, 6, 7]);
});

it('keeps only the pages in the menu and drops the subtree of a page outside the menu', function (): void {
    expect(array_map(fn (PageNode $node): int => $node->id, sampleTree()->inMenu()->flatten()))->toBe([1, 2, 3, 4]);
});

it('keeps only the published pages and drops the subtree of a draft, in menu order', function (): void {
    $published = PageStatus::Published;
    $tree = new PageTree([
        pageNode(1, '/', status: $published),
        pageNode(2, '/over-ons', [
            pageNode(3, '/over-ons/team', [pageNode(4, '/over-ons/team/jeugd', parentId: 3, depth: 2, status: $published)], parentId: 2, depth: 1),
            pageNode(5, '/over-ons/geschiedenis', parentId: 2, depth: 1, status: $published),
        ], status: $published),
        pageNode(6, '/blog', [pageNode(7, '/blog/nieuws', parentId: 6, depth: 1, status: $published)]),
        pageNode(8, '/contact', status: $published),
    ]);

    expect(array_map(fn (PageNode $node): int => $node->id, $tree->published()->flatten()))->toBe([1, 2, 5, 8]);
});

it('keeps only the pages visitors see and drops the subtree of a hidden page, in menu order', function (): void {
    $tree = new PageTree([
        pageNode(1, '/'),
        pageNode(2, '/over-ons', [
            pageNode(3, '/over-ons/team', [pageNode(4, '/over-ons/team/jeugd', parentId: 3, depth: 2)], parentId: 2, depth: 1, hidden: true),
            pageNode(5, '/over-ons/geschiedenis', parentId: 2, depth: 1),
        ]),
        pageNode(6, '/privacy', [pageNode(7, '/privacy/cookies', parentId: 6, depth: 1)], hidden: true),
        pageNode(8, '/contact'),
    ]);

    expect(array_map(fn (PageNode $node): int => $node->id, $tree->visible()->flatten()))->toBe([1, 2, 5, 8]);
});

it('keeps the published pages in the menu when both filters are combined', function (): void {
    $published = PageStatus::Published;
    $tree = new PageTree([
        pageNode(1, '/', status: $published),
        pageNode(2, '/over-ons', [pageNode(3, '/over-ons/team', parentId: 2, depth: 1, status: $published)]),
        pageNode(4, '/contact', [pageNode(5, '/contact/route', parentId: 4, depth: 1, status: $published)], inMenu: false, status: $published),
        pageNode(6, '/locatie', status: $published),
    ]);

    expect(array_map(fn (PageNode $node): int => $node->id, $tree->inMenu()->published()->flatten()))->toBe([1, 6]);
});

it('knows whether it has pages', function (): void {
    expect(sampleTree()->isEmpty())->toBeFalse()
        ->and((new PageTree([]))->isEmpty())->toBeTrue();
});

it('turns into arrays with nested children', function (): void {
    $array = (new PageTree([pageNode(2, '/over-ons', [pageNode(3, '/over-ons/team', parentId: 2, depth: 1)])]))->toArray();

    expect($array[0])->toMatchArray([
        'id' => 2,
        'title' => 'Pagina 2',
        'slug' => 'pagina-2',
        'path' => '/over-ons',
        'status' => 'draft',
        'in_menu' => true,
        'hidden' => false,
        'position' => 0,
        'parent_id' => null,
        'depth' => 0,
        'has_draft' => false,
        'is_empty' => true,
        'updated_at' => '2026-11-03T10:00:00+00:00',
        'updated_by' => null,
        'draft_started_at' => null,
    ])->and($array[0]['children'][0]['path'])->toBe('/over-ons/team');
});

it('replaces the children of a node', function (): void {
    $node = pageNode(2, '/over-ons')->withChildren([pageNode(3, '/over-ons/team', parentId: 2, depth: 1)]);

    expect($node->children[0]->id)->toBe(3)
        ->and($node->id)->toBe(2);
});

it('lifts the pages under a page into its place', function (): void {
    $tree = sampleTree()->lift(2);

    expect(array_map(fn (PageNode $node): array => [$node->id, $node->depth], $tree->flatten()))
        ->toBe([[1, 0], [3, 0], [4, 1], [5, 0], [6, 0], [7, 1]]);
});

it('keeps who changed a node last and when its draft began, at another depth and with other children', function (): void {
    $node = pageNode(2, '/over-ons', updatedBy: 7, draftStartedAt: CarbonImmutable::parse('2026-11-20 09:00:00'));

    foreach ([$node->atDepth(2), $node->withChildren([pageNode(3, '/over-ons/team', parentId: 2, depth: 1)])] as $changed) {
        expect($changed->updatedBy)->toBe(7)
            ->and($changed->draftStartedAt?->toDateTimeString())->toBe('2026-11-20 09:00:00');
    }

    expect($node->toArray())->toMatchArray(['updated_by' => 7, 'draft_started_at' => '2026-11-20T09:00:00+00:00']);
});

it('lists the editors who changed its pages last, each once and without the unknown', function (): void {
    $tree = new PageTree([
        pageNode(1, '/', updatedBy: 4),
        pageNode(2, '/over-ons', [pageNode(3, '/over-ons/team', parentId: 2, depth: 1, updatedBy: 4), pageNode(5, '/over-ons/jeugd', parentId: 2, depth: 1, updatedBy: 9)]),
    ]);

    expect($tree->editors())->toBe([4, 9])
        ->and((new PageTree([]))->editors())->toBe([]);
});

it('keeps the pages that match with the pages above them, at their own depth, without the pages under them', function (): void {
    $kept = sampleTree()->keeping(static fn (PageNode $node): bool => in_array($node->id, [3, 7], true));

    expect(array_map(fn (PageNode $node): array => [$node->id, $node->depth], $kept->flatten()))
        ->toBe([[2, 0], [3, 1], [6, 0], [7, 1]]);
});

it('keeps nothing when nothing matches', function (): void {
    expect(sampleTree()->keeping(static fn (): bool => false)->isEmpty())->toBeTrue();
});

it('keeps the menu of the site: the published pages visitors see with the menu switch on', function (): void {
    $tree = new PageTree([
        pageNode(1, '/', status: PageStatus::Published),
        pageNode(2, '/over-ons', [pageNode(3, '/over-ons/team', parentId: 2, depth: 1, status: PageStatus::Published)], status: PageStatus::Published, hidden: true),
        pageNode(4, '/jeugd'),
        pageNode(5, '/contact', status: PageStatus::Published, inMenu: false),
        pageNode(6, '/lid-worden', status: PageStatus::Published),
    ]);

    expect(array_map(fn (PageNode $node): int => $node->id, $tree->siteMenu()->flatten()))->toBe([1, 6]);
});

function menuTree(): PageTree
{
    $published = PageStatus::Published;

    return new PageTree([
        pageNode(1, '/', status: $published),
        pageNode(2, '/over-ons', [
            pageNode(3, '/over-ons/team', parentId: 2, depth: 1, status: $published),
            pageNode(4, '/over-ons/jeugd', parentId: 2, depth: 1),
            pageNode(5, '/over-ons/bestuur', parentId: 2, depth: 1, status: $published),
        ], status: $published),
        pageNode(6, '/blog', [pageNode(7, '/blog/nieuws', parentId: 6, depth: 1, status: $published)]),
        pageNode(8, '/contact', status: $published, inMenu: false),
        pageNode(9, '/bedankt', status: $published, hidden: true),
        pageNode(10, '/lid-worden', status: $published),
    ]);
}

it('says the place of a page among the pages of the menu of the site next to it', function (): void {
    expect(menuTree()->menuPlaceOf(10))->toEqual(new MenuPlace(MenuPlaceReason::Position, position: 3, total: 3))
        ->and(menuTree()->menuPlaceOf(5))->toEqual(new MenuPlace(MenuPlaceReason::Position, position: 2, total: 2));
});

it('says the place a draft takes in the menu once it is published', function (): void {
    expect(menuTree()->menuPlaceOf(4))->toEqual(new MenuPlace(MenuPlaceReason::Publish, position: 2, total: 3));
});

it('names the page above that the menu of the site leaves out', function (): void {
    expect(menuTree()->menuPlaceOf(7))->toEqual(new MenuPlace(MenuPlaceReason::Parent, parentId: 6));
});

it('gives a hidden page no place in the menu while it is hidden', function (): void {
    expect(menuTree()->menuPlaceOf(9))->toEqual(new MenuPlace(MenuPlaceReason::WhileHidden));
});

it('gives no place to a page outside the menu or a page that is not in the tree', function (): void {
    expect(menuTree()->menuPlaceOf(8))->toBeNull()
        ->and(menuTree()->menuPlaceOf(99))->toBeNull();
});
