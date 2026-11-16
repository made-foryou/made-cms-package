<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Controllers\Content;

use Illuminate\Http\JsonResponse;
use Made\CmsPackage\Content\Actions\FindMedia;
use Made\CmsPackage\Content\Actions\MoveMedia;
use Made\CmsPackage\Content\Exceptions\MediaFolderNotFound;
use Made\CmsPackage\Content\Models\Media;
use Made\CmsPackage\Http\Requests\Content\MoveManyMediaRequest;
use Made\CmsPackage\Http\Responses\MediaResponse;

/**
 * Puts the files an editor chose in the media library into a folder, or into none, all or none of them. A folder that
 * is gone comes back as a message keyed by "folder" (422), which asks for another folder: the library loads its
 * folders again itself. A file that is gone is left out of the answer.
 */
final class MoveManyMediaController
{
    public function __invoke(MoveManyMediaRequest $request): JsonResponse
    {
        try {
            $moved = MoveMedia::run($request->mediaIds(), $request->folderId());
        } catch (MediaFolderNotFound) {
            $request->reject('folder', 'folder_gone_move');
        }

        return MediaResponse::moved(FindMedia::run(array_map(static fn (Media $media): int => $media->id, $moved)));
    }
}
