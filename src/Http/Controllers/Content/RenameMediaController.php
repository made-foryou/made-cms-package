<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Controllers\Content;

use Illuminate\Http\JsonResponse;
use Made\CmsPackage\Content\Actions\FindMedia;
use Made\CmsPackage\Content\Actions\RenameMedia;
use Made\CmsPackage\Content\Models\Media;
use Made\CmsPackage\Http\Requests\Content\RenameMediaRequest;
use Made\CmsPackage\Http\Responses\MediaResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gives a file of the media library another name, and with it another address. A name without letters or digits
 * comes back as a message keyed by "name" (422).
 */
final class RenameMediaController
{
    public function __invoke(RenameMediaRequest $request, Media $media): JsonResponse
    {
        $media = RenameMedia::run($media, $request->name());

        return MediaResponse::saved(FindMedia::run([$media->id])->find($media->id) ?? abort(Response::HTTP_NOT_FOUND));
    }
}
