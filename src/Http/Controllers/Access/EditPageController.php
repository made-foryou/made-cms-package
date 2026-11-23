<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Controllers\Access;

use Illuminate\Http\Response;
use Made\CmsPackage\Access\Actions\ListScreens;
use Made\CmsPackage\Content\Actions\CompareDraft;
use Made\CmsPackage\Content\Actions\FindMediaFolder;
use Made\CmsPackage\Content\Actions\ListAllowedStripTypes;
use Made\CmsPackage\Content\Actions\ListEditableStrips;
use Made\CmsPackage\Content\Models\Page;
use Made\CmsPackage\Http\Launcher\Visitor;
use Made\CmsPackage\Http\Requests\Content\EditPageRequest;
use Made\CmsPackage\Http\Responses\EditPageResponse;
use Made\CmsPackage\Http\Session\MediaFolderMemory;

/**
 * The edit shell of a page, where an editor chooses a strip on the site to edit and builds the page from the strip
 * types its type allows; "Bewerken" of a strip on the screen of the page opens it with that strip chosen.
 */
final class EditPageController
{
    public function __invoke(EditPageRequest $request, Page $page): Response
    {
        $editor = Visitor::requireEditor($request);

        return EditPageResponse::make(
            $editor,
            ListScreens::run($editor),
            $page,
            ListEditableStrips::run($page),
            CompareDraft::run($page),
            ListAllowedStripTypes::run($page->type),
            Visitor::choosesHeadingLevels($request),
            FindMediaFolder::run(MediaFolderMemory::of($request)->id()),
            $request->chosenStrip(),
        );
    }
}
