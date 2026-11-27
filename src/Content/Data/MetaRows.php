<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Data;

use Made\CmsPackage\Content\Enums\MetaFilter;

/**
 * Every page with how it does in Google (ListMetaRows), in menu order, and the tree they come from.
 */
final readonly class MetaRows
{
    /**
     * @param  list<MetaRow>  $rows  in menu order, the pages outside the menu included
     */
    public function __construct(
        public PageTree $tree,
        public array $rows,
    ) {}

    public function find(int $id): ?MetaRow
    {
        foreach ($this->rows as $row) {
            if ($row->id === $id) {
                return $row;
            }
        }

        return null;
    }

    /**
     * The points to work through: the pages Google shows without a description or with a text it cuts off, in menu
     * order. The one place for that order, so a priority of its own (visitors, later) replaces only this.
     *
     * @return list<MetaRow>
     */
    public function round(): array
    {
        return array_values(array_filter($this->rows, static fn (MetaRow $row): bool => $row->measure->needsWork()));
    }

    /**
     * The first point after a page in menu order, also when that page is no point (any more), or null at the end.
     */
    public function nextInRound(int $after): ?int
    {
        $passed = $this->find($after) === null;

        foreach ($this->rows as $row) {
            if ($passed && $row->measure->needsWork()) {
                return $row->id;
            }

            $passed = $passed || $row->id === $after;
        }

        return null;
    }

    /**
     * @return list<MetaRow>
     */
    public function inFilter(MetaFilter $filter): array
    {
        return array_values(array_filter($this->rows, $filter->matches(...)));
    }

    public function count(MetaFilter $filter): int
    {
        return count($this->inFilter($filter));
    }
}
