<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Controllers\Content;

use Illuminate\Http\JsonResponse;
use Made\CmsPackage\Content\Actions\FindMedia;
use Made\CmsPackage\Content\Actions\ReplaceMedia;
use Made\CmsPackage\Content\Models\Media;
use Made\CmsPackage\Http\Requests\Content\ReplaceMediaRequest;
use Made\CmsPackage\Http\Responses\MediaResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Takes one file from the upload field as the new version of a file of the media library. A file the library
 * refuses, or one of another kind, comes back as a message keyed by "file" (422); a file that left the library is a
 * 404.
 */
final class ReplaceMediaController
{
    public function __invoke(ReplaceMediaRequest $request, Media $media): JsonResponse
    {
        $replaced = ReplaceMedia::run($media, $request->toData());

        return MediaResponse::saved(FindMedia::run([$replaced->id])->find($replaced->id) ?? abort(Response::HTTP_NOT_FOUND));
    }
}
