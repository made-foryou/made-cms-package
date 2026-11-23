<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Data;

use Made\CmsPackage\Content\Enums\PageFilter;

/**
 * The pages screen (OpenPagesOverview): the whole tree, the part a search and a filter show, and how the site
 * stands, counted over every page whatever the search.
 */
final readonly class PagesOverview
{
    /**
     * @param  PageTree  $tree  every page, in menu order
     * @param  PageTree  $shown  the pages that match the search, each with the pages above it; the whole tree without one
     * @param  list<int>  $matches  the ids of the pages that match the search
     * @param  array<string, list<PageNode>>  $filtered  the pages of every filter over the whole site, by filter, in menu order
     * @param  int  $inSiteMenu  how many pages the menu of the site holds
     */
    public function __construct(
        public PageTree $tree,
        public PageTree $shown,
        public array $matches,
        public PageSearch $search,
        public PagesAttention $attention,
        public array $filtered,
        public int $inSiteMenu,
    ) {}

    /**
     * The pages of a filter over the whole site, in menu order.
     *
     * @return list<PageNode>
     */
    public function inFilter(PageFilter $filter): array
    {
        return $this->filtered[$filter->value] ?? [];
    }

    /**
     * How many pages of the whole site a filter shows.
     */
    public function count(PageFilter $filter): int
    {
        return count($this->inFilter($filter));
    }

    /**
     * Whether a page matches the search itself, rather than standing in the list for a page under it.
     */
    public function matches(int $id): bool
    {
        return in_array($id, $this->matches, true);
    }
}
