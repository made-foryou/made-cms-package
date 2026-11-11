<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Controllers\Content;

use Illuminate\Http\JsonResponse;
use Made\CmsPackage\Content\Actions\ListMedia;
use Made\CmsPackage\Content\Exceptions\MediaFolderNotFound;
use Made\CmsPackage\Http\Requests\Content\ListMediaRequest;
use Made\CmsPackage\Http\Responses\MediaResponse;

/**
 * One page of the media library for the picker (and later the library): the files a filter asks for, as JSON.
 */
final class ListMediaController
{
    public function __invoke(ListMediaRequest $request): JsonResponse
    {
        $filter = $request->toData();

        try {
            $list = ListMedia::run($filter);
        } catch (MediaFolderNotFound) {
            $request->reject('folder', 'folder_gone');
        }

        return MediaResponse::listed($list, $filter->kind);
    }
}
