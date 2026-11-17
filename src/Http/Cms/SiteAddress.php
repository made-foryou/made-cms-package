<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Cms;

use Made\CmsPackage\Content\Models\Page;

/**
 * The address of a page on the site, without scheme and host and with the base path of apps in a subdirectory, so a
 * link keeps the origin of the CMS whatever `app.url` says. The pages screen has its own addresses in `PageLinks`.
 */
final class SiteAddress
{
    /** The address of the page, or null for a page without a row in `cms_routes`. */
    public static function of(Page $page): ?string
    {
        $path = $page->route?->path;

        return $path === null ? null : request()->getBaseUrl().$path;
    }
}
