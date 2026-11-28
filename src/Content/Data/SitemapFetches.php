<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Data;

use Carbon\CarbonImmutable;

/**
 * What the counts of the CMS say about Googlebot and the sitemap of a route group: the last day it fetched the
 * sitemap, the oldest day the counts still hold, how often it fetched pages of the group in that time, and how many
 * pages of the sitemap were published after the day it last fetched it.
 */
final readonly class SitemapFetches
{
    public function __construct(
        public ?CarbonImmutable $lastFetch,
        public ?CarbonImmutable $countingSince,
        public int $googlebotPages,
        public int $changedSince,
    ) {}
}
