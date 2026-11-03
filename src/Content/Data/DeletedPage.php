<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Data;

use Carbon\CarbonImmutable;
use Made\CmsPackage\Content\Enums\PageStatus;

/**
 * A deleted page that can still be restored.
 */
final readonly class DeletedPage
{
    /**
     * @param  PagePath  $formerPath  the address the page gets back when it is restored now
     * @param  bool  $parentDeleted  whether the page it lies under is deleted too; restore that one first
     */
    public function __construct(
        public int $id,
        public string $title,
        public string $slug,
        public PagePath $formerPath,
        public PageStatus $status,
        public CarbonImmutable $deletedAt,
        public ?int $parentId,
        public bool $parentDeleted,
    ) {}

    /**
     * @return array{id: int, title: string, slug: string, former_path: string, status: string, deleted_at: string, parent_id: int|null, parent_deleted: bool}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'former_path' => $this->formerPath->value,
            'status' => $this->status->value,
            'deleted_at' => $this->deletedAt->toIso8601String(),
            'parent_id' => $this->parentId,
            'parent_deleted' => $this->parentDeleted,
        ];
    }
}
