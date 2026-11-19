<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Actions;

use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Content\Data\BuiltSitemap;
use Made\CmsPackage\Content\Data\SitemapReason;
use Made\CmsPackage\Content\Data\SitePrefixes;

final class BuildSitemaps
{
    use AsAction;

    public function __construct(
        private readonly SitePrefixes $prefixes,
    ) {}

    /**
     * Builds the sitemap of every route group that serves the pages: after a change of a page and with the command.
     * Nothing while the site has no `Route::madeCms()`.
     *
     * @return list<BuiltSitemap>
     */
    public function handle(SitemapReason $reason): array
    {
        return array_map(function (string $prefix) use ($reason): BuiltSitemap {
            $sitemap = BuildSitemap::run($prefix, $reason);

            return new BuiltSitemap($prefix, $sitemap->address_count, count($sitemap->errors), $sitemap->wasRecentlyCreated);
        }, $this->prefixes->prefixes);
    }
}
