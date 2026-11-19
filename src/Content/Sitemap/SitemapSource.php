<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Sitemap;

use Made\CmsPackage\Content\Data\SitemapCandidate;

/**
 * Something the sitemap lists: the pages now, collections and images later. A source only gives what it holds to be
 * in the sitemap; whether a rule of robots.txt or the limits of Google leave it out decides BuildSitemap.
 */
interface SitemapSource
{
    /**
     * @return iterable<SitemapCandidate>
     */
    public function candidates(): iterable;
}
