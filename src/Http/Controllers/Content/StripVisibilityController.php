<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Controllers\Content;

use Illuminate\Http\JsonResponse;
use Made\CmsPackage\Content\Actions\SetStripVisibility;
use Made\CmsPackage\Content\Exceptions\StripNotFound;
use Made\CmsPackage\Content\Models\Page;
use Made\CmsPackage\Http\Requests\Content\StripVisibilityRequest;
use Made\CmsPackage\Http\Responses\StripResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Shows or hides a strip on the site, in the draft.
 */
final class StripVisibilityController
{
    public function __invoke(StripVisibilityRequest $request, Page $page, string $strip): JsonResponse
    {
        try {
            $stored = SetStripVisibility::run($page, $strip, $request->visible(), $request->editorId());
        } catch (StripNotFound) {
            abort(Response::HTTP_NOT_FOUND);
        }

        return StripResponse::saved($stored);
    }
}
