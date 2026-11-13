<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Controllers\Content;

use Made\CmsPackage\Content\Actions\MakeOriginalPreview;
use Made\CmsPackage\Content\Exceptions\MediaNotCroppable;
use Made\CmsPackage\Content\Models\Media;
use Made\CmsPackage\Http\Responses\MediaResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * The whole original of a photo, upright and scaled down, for the crop step to draw on: only for an editor, because
 * it shows what a crop left out. An original that cannot be read comes back as a message keyed by "crop" (422).
 */
final class ShowMediaOriginalController
{
    public function __invoke(Media $media): Response
    {
        try {
            return MediaResponse::original(MakeOriginalPreview::run($media));
        } catch (MediaNotCroppable) {
            abort(Response::HTTP_NOT_FOUND);
        }
    }
}
