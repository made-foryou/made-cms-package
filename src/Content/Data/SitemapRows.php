<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Data;

/**
 * Every page with its place in the sitemap of a route group (ListSitemapRows), in menu order, and the tree they come
 * from.
 */
final readonly class SitemapRows
{
    /**
     * @param  list<SitemapRow>  $rows  in menu order, the pages outside the menu included
     */
    public function __construct(
        public PageTree $tree,
        public array $rows,
    ) {}

    public function find(int $id): ?SitemapRow
    {
        foreach ($this->rows as $row) {
            if ($row->id === $id) {
                return $row;
            }
        }

        return null;
    }

    /**
     * @return list<SitemapRow>
     */
    public function in(): array
    {
        return array_values(array_filter($this->rows, static fn (SitemapRow $row): bool => $row->standing->isIn()));
    }

    /**
     * The published pages an editor keeps out of the sitemap on purpose: hidden, noindex or unticked.
     *
     * @return list<SitemapRow>
     */
    public function deliberatelyOut(): array
    {
        return array_values(array_filter($this->rows, static fn (SitemapRow $row): bool => $row->standing->isDeliberate()));
    }
}
