<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Controllers\Content;

use Illuminate\Http\JsonResponse;
use Made\CmsPackage\Content\Actions\CreateMediaFolder;
use Made\CmsPackage\Http\Requests\Content\CreateMediaFolderRequest;
use Made\CmsPackage\Http\Responses\MediaResponse;

/**
 * Adds a folder to the media library. A name it cannot take comes back as a message keyed by "name" (422).
 */
final class CreateMediaFolderController
{
    public function __invoke(CreateMediaFolderRequest $request): JsonResponse
    {
        return MediaResponse::folderCreated(CreateMediaFolder::run($request->name()));
    }
}
