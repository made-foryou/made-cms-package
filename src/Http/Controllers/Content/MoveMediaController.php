<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Controllers\Content;

use Illuminate\Http\JsonResponse;
use Made\CmsPackage\Content\Actions\FindMedia;
use Made\CmsPackage\Content\Actions\MoveMedia;
use Made\CmsPackage\Content\Exceptions\MediaFolderNotFound;
use Made\CmsPackage\Content\Models\Media;
use Made\CmsPackage\Http\Requests\Content\MoveMediaRequest;
use Made\CmsPackage\Http\Responses\MediaResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Puts a file of the media library into a folder, or into none. A folder that is gone comes back as a message keyed
 * by "folder" (422).
 */
final class MoveMediaController
{
    public function __invoke(MoveMediaRequest $request, Media $media): JsonResponse
    {
        try {
            $media = MoveMedia::run($media, $request->folderId());
        } catch (MediaFolderNotFound) {
            $request->reject('folder', 'folder_gone');
        }

        return MediaResponse::saved(FindMedia::run([$media->id])->find($media->id) ?? abort(Response::HTTP_NOT_FOUND));
    }
}
