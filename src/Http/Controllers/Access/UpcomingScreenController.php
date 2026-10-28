<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Controllers\Access;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Made\CmsPackage\Access\Actions\ListScreens;
use Made\CmsPackage\Access\Enums\Screen;
use Made\CmsPackage\Http\Launcher\Visitor;
use Made\CmsPackage\Http\Responses\UpcomingScreenResponse;

/**
 * A screen of the menu that is not built yet. A screen leaves this controller once it gets one of its own.
 */
final class UpcomingScreenController
{
    public function __invoke(Request $request, Screen $screen): Response
    {
        $editor = Visitor::requireEditor($request);

        return UpcomingScreenResponse::make($editor, ListScreens::run($editor), $screen);
    }
}
