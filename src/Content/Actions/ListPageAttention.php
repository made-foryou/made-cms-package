<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Actions;

use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Content\Data\AttentionItem;
use Made\CmsPackage\Content\Data\AttentionSources;
use Made\CmsPackage\Content\Data\PageAttention;
use Made\CmsPackage\Content\Models\Page;

final class ListPageAttention
{
    use AsAction;

    public function __construct(
        private readonly AttentionSources $sources,
    ) {}

    /**
     * What a page asks for attention, from every source (AttentionSources) and worked out when it is asked: the urgent
     * first, and within a severity in the order of the sources, so a source that joins later never shuffles the rest.
     */
    public function handle(Page $page): PageAttention
    {
        $items = [];

        foreach ($this->sources->sources as $source) {
            $items = [...$items, ...$source->items($page)];
        }

        usort($items, static fn (AttentionItem $a, AttentionItem $b): int => $a->severity->rank() <=> $b->severity->rank());

        return new PageAttention($items);
    }
}
