<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Data;

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
     * Only the pages in the menu; a page outside the menu takes the pages under it along.
     */
    public function inMenu(): self
    {
        return new self(self::filter($this->roots, static fn (PageNode $node): bool => $node->inMenu));
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
