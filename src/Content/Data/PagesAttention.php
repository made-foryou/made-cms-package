<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Data;

use Made\CmsPackage\Content\Enums\AttentionReason;

/**
 * What every page asks for attention in a list of pages, by the id of the page (ListAttentionByPage): only the items
 * that let a page stand out there.
 */
final readonly class PagesAttention
{
    /**
     * @param  array<int, PageAttention>  $pages  by the id of the page
     */
    public function __construct(
        public array $pages,
    ) {}

    /**
     * What a page asks for attention; nothing for a page it does not know, such as one made after it was read.
     */
    public function of(int $id): PageAttention
    {
        return $this->pages[$id] ?? new PageAttention([]);
    }

    /**
     * How many pages ask for attention.
     */
    public function flagged(): int
    {
        return count(array_filter($this->pages, static fn (PageAttention $attention): bool => ! $attention->isEmpty()));
    }

    /**
     * Every reason its pages ask for attention, once: the urgent first, then in the order of the reasons.
     *
     * @return list<AttentionReason>
     */
    public function reasons(): array
    {
        $found = [];

        foreach (AttentionReason::cases() as $order => $reason) {
            foreach ($this->pages as $attention) {
                foreach ($attention->items as $item) {
                    if ($item->reason === $reason) {
                        $found[$reason->value] = ['reason' => $reason, 'rank' => min($found[$reason->value]['rank'] ?? PHP_INT_MAX, $item->severity->rank()), 'order' => $order];
                    }
                }
            }
        }

        usort($found, static fn (array $a, array $b): int => [$a['rank'], $a['order']] <=> [$b['rank'], $b['order']]);

        return array_map(static fn (array $entry): AttentionReason => $entry['reason'], $found);
    }
}
