<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Controllers\Content;

use Illuminate\Http\JsonResponse;
use Made\CmsPackage\Content\Actions\ListMediaFolders;
use Made\CmsPackage\Http\Requests\Content\ListMediaFoldersRequest;
use Made\CmsPackage\Http\Responses\MediaResponse;

/**
 * The folders of the media library with the number of their files, as JSON.
 */
final class ListMediaFoldersController
{
    public function __invoke(ListMediaFoldersRequest $request): JsonResponse
    {
        return MediaResponse::folders(ListMediaFolders::run($request->kind(), $request->withoutAlt(), $request->search()));
    }
}
