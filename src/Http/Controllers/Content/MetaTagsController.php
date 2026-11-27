<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Controllers\Content;

use Illuminate\Http\Response;
use Made\CmsPackage\Access\Actions\ListScreens;
use Made\CmsPackage\Content\Actions\OpenMetaOverview;
use Made\CmsPackage\Http\Launcher\Visitor;
use Made\CmsPackage\Http\Requests\Content\ShowMetaTagsRequest;
use Made\CmsPackage\Http\Responses\MetaRedirect;
use Made\CmsPackage\Http\Responses\MetaTagsResponse;

/**
 * The Meta-overzicht: every page with its title and description in Google, and the page to work through beside it.
 */
final class MetaTagsController
{
    public function __invoke(ShowMetaTagsRequest $request): Response
    {
        $editor = Visitor::requireEditor($request);

        return MetaTagsResponse::make($editor, ListScreens::run($editor), OpenMetaOverview::run($request->toData()), MetaRedirect::justSaved($request));
    }
}
