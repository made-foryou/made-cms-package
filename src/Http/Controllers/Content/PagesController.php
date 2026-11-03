<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Controllers\Content;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Made\CmsPackage\Access\Actions\ListScreens;
use Made\CmsPackage\Content\Actions\ListDeletedPages;
use Made\CmsPackage\Content\Actions\ListPages;
use Made\CmsPackage\Http\Launcher\Visitor;
use Made\CmsPackage\Http\Responses\PagesResponse;

/**
 * The pages screen: every page in the order of the menu, and the deleted pages that can be restored.
 */
final class PagesController
{
    public function __invoke(Request $request): Response
    {
        $editor = Visitor::requireEditor($request);

        return PagesResponse::make($editor, ListScreens::run($editor), ListPages::run(), ListDeletedPages::run());
    }
}
