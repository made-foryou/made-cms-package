<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Data;

use Made\CmsPackage\Content\Enums\MetaFilter;

/**
 * What the Meta-overzicht is asked to show: a filter, the page to open and how many points this round put in order.
 */
final readonly class MetaSearch
{
    /**
     * @param  MetaFilter|null  $filter  null leaves the choice to the screen
     * @param  int|null  $pageId  the page to open; null opens the first point
     * @param  int  $done  the points this round put in order, for "3 van 10"
     */
    public function __construct(
        public ?MetaFilter $filter = null,
        public ?int $pageId = null,
        public int $done = 0,
    ) {}
}
