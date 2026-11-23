<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Data;

use Made\CmsPackage\Content\Enums\PageFilter;

/**
 * What a list of pages looks for: a piece of the name or the address, within a filter.
 */
final readonly class PageSearch
{
    /**
     * @param  string  $text  a piece of the name or the address; empty finds every page
     */
    public function __construct(
        public string $text = '',
        public PageFilter $filter = PageFilter::All,
    ) {}

    /**
     * Whether the list shows less than every page.
     */
    public function isFiltered(): bool
    {
        return $this->text !== '' || $this->filter !== PageFilter::All;
    }
}
