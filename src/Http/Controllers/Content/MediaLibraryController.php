<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Controllers\Content;

use Illuminate\Http\Response;
use Made\CmsPackage\Access\Actions\ListScreens;
use Made\CmsPackage\Content\Actions\OpenMediaLibrary;
use Made\CmsPackage\Http\Launcher\Visitor;
use Made\CmsPackage\Http\Requests\Content\ShowMediaLibraryRequest;
use Made\CmsPackage\Http\Responses\MediaLibraryResponse;

/**
 * The media library: every photo, document and video of the site, with the folder, filter and search of its address.
 */
final class MediaLibraryController
{
    public function __invoke(ShowMediaLibraryRequest $request): Response
    {
        $editor = Visitor::requireEditor($request);

        return MediaLibraryResponse::make($editor, ListScreens::run($editor), OpenMediaLibrary::run($request->toData()), $request->chosen());
    }
}
