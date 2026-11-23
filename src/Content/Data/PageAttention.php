<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Data;

use Made\CmsPackage\Content\Enums\AttentionSeverity;

/**
 * What a page asks for attention, the urgent first (ListPageAttention).
 */
final readonly class PageAttention
{
    /**
     * @param  list<AttentionItem>  $items
     */
    public function __construct(
        public array $items,
    ) {}

    public function count(): int
    {
        return count($this->items);
    }

    public function isEmpty(): bool
    {
        return $this->items === [];
    }

    /**
     * How many of the items are urgent.
     */
    public function urgent(): int
    {
        return count(array_filter($this->items, static fn (AttentionItem $item): bool => $item->severity === AttentionSeverity::High));
    }
}
