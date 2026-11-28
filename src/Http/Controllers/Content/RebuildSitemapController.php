<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Controllers\Content;

use Illuminate\Http\RedirectResponse;
use Made\CmsPackage\Content\Actions\BuildSitemaps;
use Made\CmsPackage\Http\Requests\Content\RebuildSitemapRequest;
use Made\CmsPackage\Http\Responses\SitemapRedirect;

/**
 * "Opnieuw genereren": makes the sitemap of every route group again, by hand, and comes back at the file.
 */
final class RebuildSitemapController
{
    public function __invoke(RebuildSitemapRequest $request): RedirectResponse
    {
        return SitemapRedirect::built(BuildSitemaps::run($request->toData()), $request->group());
    }
}
