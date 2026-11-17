<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Controllers\Content;

use Made\CmsPackage\Content\Actions\OpenMediaDownload;
use Made\CmsPackage\Content\Exceptions\MediaFileMissing;
use Made\CmsPackage\Content\Models\Media;
use Made\CmsPackage\Http\Responses\MediaResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * A file of the media library to save: of a photo its original as it was uploaded, of any other file the file itself.
 * A file that is not on the disk is a 404.
 */
final class DownloadMediaController
{
    public function __invoke(Media $media): Response
    {
        try {
            return MediaResponse::download(OpenMediaDownload::run($media));
        } catch (MediaFileMissing) {
            abort(Response::HTTP_NOT_FOUND);
        }
    }
}
