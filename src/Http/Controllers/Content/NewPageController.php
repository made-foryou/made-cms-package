<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Controllers\Content;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Made\CmsPackage\Access\Actions\ListScreens;
use Made\CmsPackage\Content\Actions\ListParentOptions;
use Made\CmsPackage\Content\PageTypes\PageTypes;
use Made\CmsPackage\Http\Launcher\Visitor;
use Made\CmsPackage\Http\Responses\NewPageResponse;

/**
 * The wizard for a new page, with the parent of "Page below" chosen and the page types of the site to choose from.
 */
final class NewPageController
{
    public function __invoke(Request $request, PageTypes $types): Response
    {
        $editor = Visitor::requireEditor($request);
        $parent = $request->integer('parent');

        return NewPageResponse::make($editor, ListScreens::run($editor), ListParentOptions::run(), $parent > 0 ? $parent : null, $types);
    }
}
