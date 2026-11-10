<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Controllers\Content;

use Illuminate\Http\JsonResponse;
use Made\CmsPackage\Content\Actions\FindMedia;
use Made\CmsPackage\Content\Actions\UpdateAltText;
use Made\CmsPackage\Content\Models\Media;
use Made\CmsPackage\Http\Requests\Content\UpdateAltTextRequest;
use Made\CmsPackage\Http\Responses\MediaResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gives a file of the media library its alt text, which holds wherever the file is used. A text that is too long
 * comes back as a message keyed by "alt" (422).
 */
final class UpdateAltTextController
{
    public function __invoke(UpdateAltTextRequest $request, Media $media): JsonResponse
    {
        $media = UpdateAltText::run($media, $request->alt());

        return MediaResponse::saved(FindMedia::run([$media->id])->find($media->id) ?? abort(Response::HTTP_NOT_FOUND));
    }
}
