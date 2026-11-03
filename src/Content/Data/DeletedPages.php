<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Data;

/**
 * The deleted pages that can still be restored, most recently deleted first.
 */
final readonly class DeletedPages
{
    /**
     * @param  list<DeletedPage>  $pages
     */
    public function __construct(public array $pages) {}

    public function isEmpty(): bool
    {
        return $this->pages === [];
    }

    /**
     * @return list<array{id: int, title: string, slug: string, former_path: string, status: string, deleted_at: string, parent_id: int|null, parent_deleted: bool}>
     */
    public function toArray(): array
    {
        return array_map(static fn (DeletedPage $page): array => $page->toArray(), $this->pages);
    }
}
