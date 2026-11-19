<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Controllers\Content;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Made\CmsPackage\Content\Actions\OpenSitemap;
use Made\CmsPackage\Http\Responses\SiteFileResponse;
use Made\CmsPackage\Http\Routing\SiteRoutes;

/**
 * The sitemap of the route group this address lies in, for anyone: Google, other search engines, a visitor.
 */
final class ShowSitemapController
{
    public function __invoke(Request $request): Response
    {
        return SiteFileResponse::sitemap(OpenSitemap::run(SiteRoutes::prefix($request->route())), $request);
    }
}
