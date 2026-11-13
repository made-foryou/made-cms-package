<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Controllers\Content;

use Illuminate\Http\JsonResponse;
use Made\CmsPackage\Content\Actions\DescribeMediaCrop;
use Made\CmsPackage\Content\Exceptions\MediaNotCroppable;
use Made\CmsPackage\Content\Models\Media;
use Made\CmsPackage\Http\Responses\MediaResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * A photo as the crop step shows it: the file, its original and how it was cut from it. A file without an original
 * is no photo to crop (404).
 */
final class ShowMediaCropController
{
    public function __invoke(Media $media): JsonResponse
    {
        try {
            return MediaResponse::crop(DescribeMediaCrop::run($media));
        } catch (MediaNotCroppable) {
            abort(Response::HTTP_NOT_FOUND);
        }
    }
}
