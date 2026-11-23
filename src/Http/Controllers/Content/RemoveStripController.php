<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Controllers\Content;

use Illuminate\Http\JsonResponse;
use Made\CmsPackage\Content\Actions\ListEditableStrips;
use Made\CmsPackage\Content\Actions\RemoveStrip;
use Made\CmsPackage\Content\Exceptions\StripNotFound;
use Made\CmsPackage\Content\Models\Page;
use Made\CmsPackage\Http\Requests\Content\RemoveStripRequest;
use Made\CmsPackage\Http\Responses\PageStripsResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Removes a strip from the draft. The answer holds the strip as it was stored, so the edit shell can put it back.
 */
final class RemoveStripController
{
    public function __invoke(RemoveStripRequest $request, Page $page, string $strip): JsonResponse
    {
        try {
            $removed = RemoveStrip::run($page, $strip, $request->editorId());
        } catch (StripNotFound) {
            abort(Response::HTTP_NOT_FOUND);
        }

        return PageStripsResponse::removed(ListEditableStrips::run($page), $removed);
    }
}
