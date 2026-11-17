<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Controllers\Content;

use Illuminate\Http\JsonResponse;
use Made\CmsPackage\Access\Actions\NameEditors;
use Made\CmsPackage\Content\Actions\DescribeMedia;
use Made\CmsPackage\Content\Models\Media;
use Made\CmsPackage\Http\Responses\MediaResponse;

/**
 * A file as the side column of the media library shows it: the file, the pages it is used on and its history with the
 * names of the editors in it, as JSON.
 */
final class ShowMediaController
{
    public function __invoke(Media $media): JsonResponse
    {
        $description = DescribeMedia::run($media);

        return MediaResponse::described($description, NameEditors::run($description->history->editors()));
    }
}
