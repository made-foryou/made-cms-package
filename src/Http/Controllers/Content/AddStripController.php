<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Controllers\Content;

use Illuminate\Http\JsonResponse;
use Made\CmsPackage\Content\Actions\AddStrip;
use Made\CmsPackage\Content\Actions\ListEditableStrips;
use Made\CmsPackage\Content\Exceptions\InvalidStripPosition;
use Made\CmsPackage\Content\Exceptions\UnknownStripType;
use Made\CmsPackage\Content\Models\Page;
use Made\CmsPackage\Http\Requests\Content\AddStripRequest;
use Made\CmsPackage\Http\Responses\PageStripsResponse;

/**
 * Places a new strip of a type from the catalogue in the draft, filled with the defaults of its type.
 */
final class AddStripController
{
    public function __invoke(AddStripRequest $request, Page $page): JsonResponse
    {
        try {
            $strip = AddStrip::run($page, $request->type(), $request->position());
        } catch (UnknownStripType) {
            $request->reject('type', 'type');
        } catch (InvalidStripPosition) {
            $request->reject('position', 'position');
        }

        return PageStripsResponse::added(ListEditableStrips::run($page), $strip);
    }
}
