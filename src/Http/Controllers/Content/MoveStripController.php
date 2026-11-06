<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Controllers\Content;

use Illuminate\Http\JsonResponse;
use Made\CmsPackage\Content\Actions\ListEditableStrips;
use Made\CmsPackage\Content\Actions\MoveStrip;
use Made\CmsPackage\Content\Exceptions\InvalidStripPosition;
use Made\CmsPackage\Content\Exceptions\StripNotFound;
use Made\CmsPackage\Content\Models\Page;
use Made\CmsPackage\Http\Requests\Content\MoveStripRequest;
use Made\CmsPackage\Http\Responses\PageStripsResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Moves a strip of the draft to another place on the page.
 */
final class MoveStripController
{
    public function __invoke(MoveStripRequest $request, Page $page, string $strip): JsonResponse
    {
        try {
            MoveStrip::run($page, $strip, $request->position());
        } catch (StripNotFound) {
            abort(Response::HTTP_NOT_FOUND);
        } catch (InvalidStripPosition) {
            $request->reject('position', 'position');
        }

        return PageStripsResponse::moved(ListEditableStrips::run($page));
    }
}
