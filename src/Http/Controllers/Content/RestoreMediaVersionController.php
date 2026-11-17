<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Controllers\Content;

use Illuminate\Http\JsonResponse;
use Made\CmsPackage\Content\Actions\FindMedia;
use Made\CmsPackage\Content\Actions\RestoreMediaVersion;
use Made\CmsPackage\Content\Data\MediaHistory;
use Made\CmsPackage\Content\Models\Media;
use Made\CmsPackage\Http\Requests\Content\RestoreMediaVersionRequest;
use Made\CmsPackage\Http\Responses\MediaResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Puts the version of a file that waits back. A file without one comes back as a message keyed by "version" (422).
 */
final class RestoreMediaVersionController
{
    public function __invoke(RestoreMediaVersionRequest $request, Media $media): JsonResponse
    {
        $restored = RestoreMediaVersion::run($media);

        return MediaResponse::restored(
            FindMedia::run([$restored->id])->find($restored->id) ?? abort(Response::HTTP_NOT_FOUND),
            MediaHistory::of($restored),
        );
    }
}
