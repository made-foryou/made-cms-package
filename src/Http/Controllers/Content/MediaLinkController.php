<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Controllers\Content;

use Illuminate\Http\RedirectResponse;
use Made\CmsPackage\Content\Actions\FindLinkedMedia;
use Made\CmsPackage\Http\Responses\MediaResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * The lasting link of a file of the media library, for anyone: it sends the visitor to the address the file has now,
 * whatever version, crop or name it has. The name in the link is only there to read; a file that left the library is
 * a 404.
 */
final class MediaLinkController
{
    public function __invoke(string $ulid): RedirectResponse
    {
        return MediaResponse::linked(FindLinkedMedia::run($ulid) ?? abort(Response::HTTP_NOT_FOUND));
    }
}
