<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Controllers\Content;

use Made\CmsPackage\Http\Requests\Content\RememberMediaFolderRequest;
use Made\CmsPackage\Http\Responses\MediaResponse;
use Made\CmsPackage\Http\Session\MediaFolderMemory;
use Symfony\Component\HttpFoundation\Response;

/**
 * Remembers the folder an editor opened in the picker for this session: the next edit shell opens the picker there
 * and uploads a photo into it.
 */
final class RememberMediaFolderController
{
    public function __invoke(RememberMediaFolderRequest $request): Response
    {
        MediaFolderMemory::of($request)->remember($request->folderId());

        return MediaResponse::remembered();
    }
}
