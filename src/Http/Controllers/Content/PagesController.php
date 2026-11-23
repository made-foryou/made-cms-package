<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Controllers\Content;

use Illuminate\Http\Response;
use Made\CmsPackage\Access\Actions\ListScreens;
use Made\CmsPackage\Access\Actions\NameEditors;
use Made\CmsPackage\Content\Actions\ListDeletedPages;
use Made\CmsPackage\Content\Actions\OpenPagesOverview;
use Made\CmsPackage\Http\Launcher\Visitor;
use Made\CmsPackage\Http\Requests\Content\ShowPagesRequest;
use Made\CmsPackage\Http\Responses\PagesResponse;

/**
 * The pages screen: every page in the order of the menu with how it stands, and the deleted pages that can be
 * restored.
 */
final class PagesController
{
    public function __invoke(ShowPagesRequest $request): Response
    {
        $editor = Visitor::requireEditor($request);
        $overview = OpenPagesOverview::run($request->toData());

        return PagesResponse::make($editor, ListScreens::run($editor), $overview, NameEditors::run($overview->shown->editors()), ListDeletedPages::run());
    }
}
