<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Controllers\Content;

use Illuminate\Http\JsonResponse;
use Made\CmsPackage\Content\Actions\FindMedia;
use Made\CmsPackage\Content\Actions\UploadMedia;
use Made\CmsPackage\Content\Exceptions\MediaFolderNotFound;
use Made\CmsPackage\Http\Requests\Content\UploadMediaRequest;
use Made\CmsPackage\Http\Responses\MediaResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Takes one file from the upload field into the media library. A file the library refuses (too large, an unknown
 * type, an svg that does more than draw) comes back as a message keyed by "file" (422), so the field shows it with
 * that file and goes on with the others.
 */
final class UploadMediaController
{
    public function __invoke(UploadMediaRequest $request): JsonResponse
    {
        try {
            $media = UploadMedia::run($request->toData());
        } catch (MediaFolderNotFound) {
            $request->reject('folder', 'folder_gone');
        }

        return MediaResponse::uploaded(FindMedia::run([$media->id])->find($media->id) ?? abort(Response::HTTP_NOT_FOUND));
    }
}
