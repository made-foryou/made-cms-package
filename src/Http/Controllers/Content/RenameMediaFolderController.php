<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Controllers\Content;

use Illuminate\Http\JsonResponse;
use Made\CmsPackage\Content\Actions\RenameMediaFolder;
use Made\CmsPackage\Content\Exceptions\MediaFolderNotFound;
use Made\CmsPackage\Content\Models\MediaFolder;
use Made\CmsPackage\Http\Requests\Content\RenameMediaFolderRequest;
use Made\CmsPackage\Http\Responses\MediaResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gives a folder of the media library another name. A name it cannot take comes back as a message keyed by "name"
 * (422), a folder that went in the meantime as 404.
 */
final class RenameMediaFolderController
{
    public function __invoke(RenameMediaFolderRequest $request, MediaFolder $folder): JsonResponse
    {
        try {
            return MediaResponse::folder(RenameMediaFolder::run($folder, $request->name()));
        } catch (MediaFolderNotFound) {
            abort(Response::HTTP_NOT_FOUND);
        }
    }
}
