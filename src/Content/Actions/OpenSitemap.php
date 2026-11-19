<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Actions;

use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Content\Data\SitemapReason;
use Made\CmsPackage\Content\Enums\SitemapCause;
use Made\CmsPackage\Content\Models\Sitemap;

final class OpenSitemap
{
    use AsAction;

    /**
     * The sitemap of the route group with this prefix as it is served: the newest one stored, or one built now when
     * the group has none yet (a new site, a new group), so robots never get a 404 for it.
     */
    public function handle(string $prefix): Sitemap
    {
        return Sitemap::query()->forPrefix($prefix)->newestFirst()->first()
            ?? BuildSitemap::run($prefix, new SitemapReason(SitemapCause::Requested));
    }
}
