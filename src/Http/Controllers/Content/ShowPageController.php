<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Controllers\Content;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Made\CmsPackage\Access\Actions\ListScreens;
use Made\CmsPackage\Content\Actions\ListMenuPages;
use Made\CmsPackage\Content\Actions\ListPages;
use Made\CmsPackage\Content\Actions\ListParentOptions;
use Made\CmsPackage\Content\Models\Page;
use Made\CmsPackage\Http\Launcher\Visitor;
use Made\CmsPackage\Http\Responses\PageCreatedResponse;
use Made\CmsPackage\Http\Responses\PageRedirect;
use Made\CmsPackage\Http\Responses\ShowPageResponse;

/**
 * One page: its basics, the pages under it and removing it. Right after the wizard: that it is ready.
 */
final class ShowPageController
{
    public function __invoke(Request $request, Page $page): Response
    {
        $editor = Visitor::requireEditor($request);
        $screens = ListScreens::run($editor);
        $pages = ListPages::run();

        if (PageRedirect::justCreated($request, $page)) {
            return PageCreatedResponse::make($editor, $screens, $page, $pages);
        }

        return ShowPageResponse::make($editor, $screens, $page, $pages, ListParentOptions::run($page), ListMenuPages::run());
    }
}
