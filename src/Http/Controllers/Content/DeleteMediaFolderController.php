<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Controllers\Content;

use Made\CmsPackage\Content\Actions\DeleteMediaFolder;
use Made\CmsPackage\Content\Models\MediaFolder;
use Made\CmsPackage\Http\Requests\Content\DeleteMediaFolderRequest;
use Made\CmsPackage\Http\Responses\MediaResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Takes an empty folder out of the media library. A folder that holds files comes back as a message keyed by "folder"
 * with their number (422).
 */
final class DeleteMediaFolderController
{
    public function __invoke(DeleteMediaFolderRequest $request, MediaFolder $folder): Response
    {
        DeleteMediaFolder::run($folder);

        return MediaResponse::folderDeleted();
    }
}
