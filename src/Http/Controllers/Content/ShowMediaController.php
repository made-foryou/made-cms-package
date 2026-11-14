<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Controllers\Content;

use Illuminate\Http\JsonResponse;
use Made\CmsPackage\Content\Actions\DescribeMedia;
use Made\CmsPackage\Content\Models\Media;
use Made\CmsPackage\Http\Responses\MediaResponse;

/**
 * A file as the side column of the media library shows it: the file and the pages it is used on, as JSON.
 */
final class ShowMediaController
{
    public function __invoke(Media $media): JsonResponse
    {
        return MediaResponse::described(DescribeMedia::run($media));
    }
}
