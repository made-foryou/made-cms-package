<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Controllers\Access;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Made\CmsPackage\Access\Actions\ListScreens;
use Made\CmsPackage\Http\Launcher\Visitor;
use Made\CmsPackage\Http\Responses\StartPageResponse;

/**
 * The start page of the CMS: what the editor's roles have to do today, and the menu of their screens.
 */
final class StartPageController
{
    public function __invoke(Request $request): Response
    {
        $editor = Visitor::requireEditor($request);

        return StartPageResponse::make($editor, ListScreens::run($editor));
    }
}
