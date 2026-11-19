<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Data;

use Made\CmsPackage\Content\Sitemap\SitemapSource;

/**
 * The sources of the sitemap, in the order their addresses appear: the pages now; collections and images later.
 */
final readonly class SitemapSources
{
    /**
     * @param  list<SitemapSource>  $sources
     */
    public function __construct(
        public array $sources,
    ) {}
}
