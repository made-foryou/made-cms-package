<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Controllers\Content;

use Illuminate\Http\JsonResponse;
use Made\CmsPackage\Content\Actions\DeleteMedia;
use Made\CmsPackage\Http\Requests\Content\DeleteMediaRequest;
use Made\CmsPackage\Http\Responses\MediaResponse;

/**
 * Deletes the chosen file or the selected files of the media library that no page uses, and keeps the rest: the answer
 * says which went, which stayed and why, in words.
 */
final class DeleteMediaController
{
    public function __invoke(DeleteMediaRequest $request): JsonResponse
    {
        return MediaResponse::deleted(DeleteMedia::run($request->mediaIds()));
    }
}
