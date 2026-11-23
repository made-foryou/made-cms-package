<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Data;

use Carbon\CarbonImmutable;
use Made\CmsPackage\Content\Enums\PageStatus;

/**
 * A page in the tree of the site, with the pages under it in menu order.
 *
 * @phpstan-type PageNodeArray array{id: int, title: string, slug: string, path: string|null, status: string, in_menu: bool, hidden: bool, position: int, parent_id: int|null, depth: int, has_draft: bool, is_empty: bool, updated_at: string, updated_by: int|null, draft_started_at: string|null, children: list<mixed>}
 */
final readonly class PageNode
{
    /**
     * @param  PagePath|null  $path  null for a page without an address
     * @param  bool  $hidden  whether visitors only reach the page through its address
     * @param  int  $depth  0 for a page at the top
     * @param  bool  $hasDraft  whether editors changed the page since it was published
     * @param  bool  $isEmpty  whether the page has no strips, published or in its draft
     * @param  int|null  $updatedBy  the editor who changed the page last; null when that is not known
     * @param  CarbonImmutable|null  $draftStartedAt  when the draft that is there began; null without one
     * @param  list<PageNode>  $children  in menu order
     */
    public function __construct(
        public int $id,
        public string $title,
        public string $slug,
        public ?PagePath $path,
        public PageStatus $status,
        public bool $inMenu,
        public bool $hidden,
        public int $position,
        public ?int $parentId,
        public int $depth,
        public bool $hasDraft,
        public bool $isEmpty,
        public CarbonImmutable $updatedAt,
        public ?int $updatedBy = null,
        public ?CarbonImmutable $draftStartedAt = null,
        public array $children = [],
    ) {}

    /**
     * @param  list<PageNode>  $children
     */
    public function withChildren(array $children): self
    {
        return $this->with($this->depth, $children);
    }

    /**
     * The node at another depth, the pages under it along.
     */
    public function atDepth(int $depth): self
    {
        return $this->with($depth, array_map(static fn (PageNode $child): PageNode => $child->atDepth($depth + 1), $this->children));
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
            'hidden' => $this->hidden,
            'position' => $this->position,
            'parent_id' => $this->parentId,
            'depth' => $this->depth,
            'has_draft' => $this->hasDraft,
            'is_empty' => $this->isEmpty,
            'updated_at' => $this->updatedAt->toIso8601String(),
            'updated_by' => $this->updatedBy,
            'draft_started_at' => $this->draftStartedAt?->toIso8601String(),
            'children' => array_map(static fn (PageNode $child): array => $child->toArray(), $this->children),
        ];
    }

    /**
     * @param  list<PageNode>  $children
     */
    private function with(int $depth, array $children): self
    {
        return new self(
            id: $this->id,
            title: $this->title,
            slug: $this->slug,
            path: $this->path,
            status: $this->status,
            inMenu: $this->inMenu,
            hidden: $this->hidden,
            position: $this->position,
            parentId: $this->parentId,
            depth: $depth,
            hasDraft: $this->hasDraft,
            isEmpty: $this->isEmpty,
            updatedAt: $this->updatedAt,
            updatedBy: $this->updatedBy,
            draftStartedAt: $this->draftStartedAt,
            children: $children,
        );
    }
}
