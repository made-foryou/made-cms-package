<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Data;

use Carbon\CarbonImmutable;
use Made\CmsPackage\Content\Enums\PageStatus;

/**
 * A page in the tree of the site, with the pages under it in menu order.
 *
 * @phpstan-type PageNodeArray array{id: int, title: string, slug: string, path: string|null, status: string, in_menu: bool, position: int, parent_id: int|null, depth: int, has_draft: bool, is_empty: bool, updated_at: string, children: list<mixed>}
 */
final readonly class PageNode
{
    /**
     * @param  PagePath|null  $path  null for a page without an address
     * @param  int  $depth  0 for a page at the top
     * @param  bool  $hasDraft  whether editors changed the page since it was published
     * @param  bool  $isEmpty  whether the page has no strips, published or in its draft
     * @param  list<PageNode>  $children  in menu order
     */
    public function __construct(
        public int $id,
        public string $title,
        public string $slug,
        public ?PagePath $path,
        public PageStatus $status,
        public bool $inMenu,
        public int $position,
        public ?int $parentId,
        public int $depth,
        public bool $hasDraft,
        public bool $isEmpty,
        public CarbonImmutable $updatedAt,
        public array $children = [],
    ) {}

    /**
     * @param  list<PageNode>  $children
     */
    public function withChildren(array $children): self
    {
        return new self(
            $this->id, $this->title, $this->slug, $this->path, $this->status, $this->inMenu, $this->position,
            $this->parentId, $this->depth, $this->hasDraft, $this->isEmpty, $this->updatedAt, $children,
        );
    }

    /**
     * The node at another depth, the pages under it along.
     */
    public function atDepth(int $depth): self
    {
        return new self(
            $this->id, $this->title, $this->slug, $this->path, $this->status, $this->inMenu, $this->position,
            $this->parentId, $depth, $this->hasDraft, $this->isEmpty, $this->updatedAt,
            array_map(static fn (PageNode $child): PageNode => $child->atDepth($depth + 1), $this->children),
        );
    }

    /**
     * @return PageNodeArray
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'path' => $this->path?->value,
            'status' => $this->status->value,
            'in_menu' => $this->inMenu,
            'position' => $this->position,
            'parent_id' => $this->parentId,
            'depth' => $this->depth,
            'has_draft' => $this->hasDraft,
            'is_empty' => $this->isEmpty,
            'updated_at' => $this->updatedAt->toIso8601String(),
            'children' => array_map(static fn (PageNode $child): array => $child->toArray(), $this->children),
        ];
    }
}
