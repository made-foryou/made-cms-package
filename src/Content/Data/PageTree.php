<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Data;

use Made\CmsPackage\Content\Enums\MenuPlaceReason;
use Made\CmsPackage\Content\Enums\PageStatus;

/**
 * The pages of the site as a tree, in menu order.
 *
 * @phpstan-import-type PageNodeArray from PageNode
 */
final readonly class PageTree
{
    /**
     * @param  list<PageNode>  $roots  the pages at the top, in menu order
     */
    public function __construct(public array $roots) {}

    public function isEmpty(): bool
    {
        return $this->roots === [];
    }

    /**
     * Every page, each followed by the pages under it: the order of an indented list.
     *
     * @return list<PageNode>
     */
    public function flatten(): array
    {
        return self::flattenNodes($this->roots);
    }

    public function find(int $id): ?PageNode
    {
        foreach ($this->flatten() as $node) {
            if ($node->id === $id) {
                return $node;
            }
        }

        return null;
    }

    /**
     * The tree without a page and the pages under it.
     */
    public function without(int $id): self
    {
        return new self(self::filter($this->roots, static fn (PageNode $node): bool => $node->id !== $id));
    }

    /**
     * The tree without a page, with the pages under it in its place, one level up.
     */
    public function lift(int $id): self
    {
        return new self(self::liftFrom($this->roots, $id, 0));
    }

    /**
     * Only the pages with the menu switch on, drafts included: the tree of the CMS. A page outside the menu
     * takes the pages under it along. The menu of the site is ListMenuPages.
     */
    public function inMenu(): self
    {
        return new self(self::filter($this->roots, static fn (PageNode $node): bool => $node->inMenu));
    }

    /**
     * Only the published pages; a draft takes the pages under it along.
     */
    public function published(): self
    {
        return new self(self::filter($this->roots, static fn (PageNode $node): bool => $node->status === PageStatus::Published));
    }

    /**
     * Only the pages visitors see; a hidden page takes the pages under it along, as the menu has no place for them.
     */
    public function visible(): self
    {
        return new self(self::filter($this->roots, static fn (PageNode $node): bool => ! $node->hidden));
    }

    /**
     * The menu of the site: the published pages visitors see with the menu switch on, in menu order. A draft, a
     * hidden page or a page outside the menu takes the pages under it along.
     */
    public function siteMenu(): self
    {
        return $this->inMenu()->published()->visible();
    }

    /**
     * The place of a page in the menu of the site; null for a page outside the menu or not in the tree. Only the pages
     * the site shows in its menu count: a hidden page has no place while it is hidden, a draft gets the place it takes
     * once published, and a page under a page the menu of the site leaves out names that page.
     */
    public function menuPlaceOf(int $id): ?MenuPlace
    {
        $node = $this->find($id);

        if ($node === null || ! $node->inMenu) {
            return null;
        }

        if ($node->hidden) {
            return new MenuPlace(MenuPlaceReason::WhileHidden);
        }

        $shown = array_fill_keys(array_map(static fn (PageNode $item): int => $item->id, $this->siteMenu()->flatten()), true);
        $parent = $node->parentId === null ? null : $this->find($node->parentId);

        if ($parent !== null && ! isset($shown[$parent->id])) {
            return new MenuPlace(MenuPlaceReason::Parent, parentId: $parent->id);
        }

        $ids = array_values(array_map(
            static fn (PageNode $sibling): int => $sibling->id,
            array_filter(
                $parent === null ? $this->roots : $parent->children,
                static fn (PageNode $sibling): bool => $sibling->id === $node->id || isset($shown[$sibling->id]),
            ),
        ));

        return new MenuPlace(
            isset($shown[$node->id]) ? MenuPlaceReason::Position : MenuPlaceReason::Publish,
            position: (int) array_search($node->id, $ids, true) + 1,
            total: count($ids),
        );
    }

    /**
     * Only the pages that match, each with the pages above it so its place stays clear, at their own depth; a page
     * that matches does not bring the pages under it along.
     *
     * @param  callable(PageNode): bool  $matches
     */
    public function keeping(callable $matches): self
    {
        return new self(self::keep($this->roots, $matches));
    }

    /**
     * The editors who changed its pages last, each once in menu order, without the unknown: the ids a screen names.
     *
     * @return list<int>
     */
    public function editors(): array
    {
        return array_values(array_unique(array_filter(
            array_map(static fn (PageNode $node): ?int => $node->updatedBy, $this->flatten()),
            static fn (?int $id): bool => $id !== null,
        )));
    }

    /**
     * @return list<PageNodeArray>
     */
    public function toArray(): array
    {
        return array_map(static fn (PageNode $node): array => $node->toArray(), $this->roots);
    }

    /**
     * @param  list<PageNode>  $nodes
     * @return list<PageNode>
     */
    private static function flattenNodes(array $nodes): array
    {
        $flat = [];

        foreach ($nodes as $node) {
            $flat[] = $node;
            array_push($flat, ...self::flattenNodes($node->children));
        }

        return $flat;
    }

    /**
     * @param  list<PageNode>  $nodes
     * @return list<PageNode>
     */
    private static function liftFrom(array $nodes, int $id, int $depth): array
    {
        $kept = [];

        foreach ($nodes as $node) {
            if ($node->id === $id) {
                array_push($kept, ...array_map(static fn (PageNode $child): PageNode => $child->atDepth($depth), $node->children));
            } else {
                $kept[] = $node->withChildren(self::liftFrom($node->children, $id, $depth + 1));
            }
        }

        return $kept;
    }

    /**
     * @param  list<PageNode>  $nodes
     * @param  callable(PageNode): bool  $matches
     * @return list<PageNode>
     */
    private static function keep(array $nodes, callable $matches): array
    {
        $kept = [];

        foreach ($nodes as $node) {
            $children = self::keep($node->children, $matches);

            if ($children !== [] || $matches($node)) {
                $kept[] = $node->withChildren($children);
            }
        }

        return $kept;
    }

    /**
     * @param  list<PageNode>  $nodes
     * @param  callable(PageNode): bool  $keep
     * @return list<PageNode>
     */
    private static function filter(array $nodes, callable $keep): array
    {
        $kept = [];

        foreach ($nodes as $node) {
            if ($keep($node)) {
                $kept[] = $node->withChildren(self::filter($node->children, $keep));
            }
        }

        return $kept;
    }
}
