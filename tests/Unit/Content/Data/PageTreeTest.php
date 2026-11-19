<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Made\CmsPackage\Content\Data\PageNode;
use Made\CmsPackage\Content\Data\PagePath;
use Made\CmsPackage\Content\Data\PageTree;
use Made\CmsPackage\Content\Enums\PageStatus;

/**
 * @param  list<PageNode>  $children
 */
function pageNode(int $id, string $path, array $children = [], bool $inMenu = true, ?int $parentId = null, int $depth = 0, PageStatus $status = PageStatus::Draft, bool $hidden = false): PageNode
{
    return new PageNode(
        id: $id,
        title: 'Pagina '.$id,
        slug: 'pagina-'.$id,
        path: PagePath::fromString($path),
        status: $status,
        inMenu: $inMenu,
        hidden: $hidden,
        position: 0,
        parentId: $parentId,
        depth: $depth,
        hasDraft: false,
        isEmpty: true,
        updatedAt: CarbonImmutable::parse('2026-11-03 10:00:00'),
        children: $children,
    );
}

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
