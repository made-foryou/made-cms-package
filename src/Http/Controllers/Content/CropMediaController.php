<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Controllers\Content;

use Illuminate\Http\JsonResponse;
use Made\CmsPackage\Content\Actions\CropImage;
use Made\CmsPackage\Content\Actions\FindMedia;
use Made\CmsPackage\Content\Exceptions\MediaNotCroppable;
use Made\CmsPackage\Content\Models\Media;
use Made\CmsPackage\Http\Requests\Content\CropMediaRequest;
use Made\CmsPackage\Http\Responses\MediaResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cuts a photo from its original as the editor drew it in the crop step. An area outside the photo, or an original
 * that cannot be read, comes back as a message keyed by "crop" (422).
 */
final class CropMediaController
{
    public function __invoke(CropMediaRequest $request, Media $media): JsonResponse
    {
        try {
            $media = CropImage::run($media, $request->toData());
        } catch (MediaNotCroppable) {
            $request->reject('crop', 'not_croppable');
        }

        return MediaResponse::saved(FindMedia::run([$media->id])->find($media->id) ?? abort(Response::HTTP_NOT_FOUND));
    }
}
