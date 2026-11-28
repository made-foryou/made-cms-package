<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Data;

use Made\CmsPackage\Content\Models\Sitemap;

/**
 * The screen of the sitemap of a route group: the groups that serve pages, the group it shows, the sitemap stored for
 * it (null when no route serves the pages) with the name of the page whose change built it, every page with its place
 * in it, the files of the public folder that go before it and what Googlebot did with it.
 */
final readonly class SitemapOverview
{
    /**
     * @param  list<string>  $prefixes  the prefixes of the route groups that serve pages, the root first
     * @param  string  $prefix  the group it shows, "" at the root
     */
    public function __construct(
        public array $prefixes,
        public string $prefix,
        public ?Sitemap $sitemap,
        public ?string $causePage,
        public SitemapRows $rows,
        public ?PublicSiteFiles $files,
        public SitemapFetches $fetches,
    ) {}
}
