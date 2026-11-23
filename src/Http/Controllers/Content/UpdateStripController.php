<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Controllers\Content;

use Illuminate\Http\JsonResponse;
use Made\CmsPackage\Content\Actions\UpdateStrip;
use Made\CmsPackage\Content\Exceptions\StripNotFound;
use Made\CmsPackage\Content\Exceptions\UnknownStripType;
use Made\CmsPackage\Content\Models\Page;
use Made\CmsPackage\Http\Requests\Content\UpdateStripRequest;
use Made\CmsPackage\Http\Responses\StripResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Saves the values of a strip from the side panel in the draft. A value the strip type refuses comes back as a
 * message under its field (422), keyed by the path of the field.
 */
final class UpdateStripController
{
    public function __invoke(UpdateStripRequest $request, Page $page, string $strip): JsonResponse
    {
        try {
            $stored = UpdateStrip::run($page, $strip, $request->values(), $request->choosesLevels(), $request->editorId());
        } catch (StripNotFound|UnknownStripType) {
            abort(Response::HTTP_NOT_FOUND);
        }

        return StripResponse::saved($stored);
    }
}
