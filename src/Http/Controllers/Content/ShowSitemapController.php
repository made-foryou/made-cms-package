<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Controllers\Content;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Made\CmsPackage\Content\Actions\OpenSitemap;
use Made\CmsPackage\Content\Data\PagePath;
use Made\CmsPackage\Content\Enums\HitOutcome;
use Made\CmsPackage\Http\Responses\SiteFileResponse;
use Made\CmsPackage\Http\Routing\AddressHits;
use Made\CmsPackage\Http\Routing\SiteRoutes;

/**
 * The sitemap of the route group this address lies in, for anyone: Google, other search engines, a visitor. Every
 * fetch is counted, also one that is told nothing changed, so the CMS knows when Google last came for it.
 */
final class ShowSitemapController
{
    public function __invoke(Request $request): Response
    {
        AddressHits::count($request, HitOutcome::Served, PagePath::fromString('sitemap.xml'));

        return SiteFileResponse::sitemap(OpenSitemap::run(SiteRoutes::prefix($request->route())), $request);
    }
}
