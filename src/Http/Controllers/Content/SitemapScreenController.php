<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Controllers\Content;

use Illuminate\Http\Response;
use Made\CmsPackage\Access\Actions\ListScreens;
use Made\CmsPackage\Access\Actions\NameEditors;
use Made\CmsPackage\Content\Actions\OpenSitemapOverview;
use Made\CmsPackage\Http\Launcher\Visitor;
use Made\CmsPackage\Http\Requests\Content\ShowSitemapScreenRequest;
use Made\CmsPackage\Http\Responses\SitemapRedirect;
use Made\CmsPackage\Http\Responses\SitemapScreenResponse;

/**
 * The screen of the sitemap: what Google gets, when it fetched it, the file itself and every page with whether it is
 * in it.
 */
final class SitemapScreenController
{
    public function __invoke(ShowSitemapScreenRequest $request): Response
    {
        $editor = Visitor::requireEditor($request);
        $overview = OpenSitemapOverview::run($request->toData());
        $builder = $overview->sitemap?->editor_id;

        return SitemapScreenResponse::make(
            $editor,
            ListScreens::run($editor),
            $overview,
            $builder === null ? null : NameEditors::run([$builder])[$builder] ?? null,
            SitemapRedirect::justSaved($request),
            SitemapRedirect::justBuilt($request),
        );
    }
}
