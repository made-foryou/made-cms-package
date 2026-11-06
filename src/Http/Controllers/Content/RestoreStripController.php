<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Controllers\Content;

use Illuminate\Http\JsonResponse;
use Made\CmsPackage\Content\Actions\ListEditableStrips;
use Made\CmsPackage\Content\Actions\RestoreStrip;
use Made\CmsPackage\Content\Exceptions\InvalidStripPosition;
use Made\CmsPackage\Content\Exceptions\StripAlreadyOnPage;
use Made\CmsPackage\Content\Exceptions\UnknownStripType;
use Made\CmsPackage\Content\Models\Page;
use Made\CmsPackage\Http\Requests\Content\RestoreStripRequest;
use Made\CmsPackage\Http\Responses\PageStripsResponse;

/**
 * Puts a removed strip back in the draft ("Ongedaan maken"). Values its type refuses come back as messages keyed by
 * the path of the field (422).
 */
final class RestoreStripController
{
    public function __invoke(RestoreStripRequest $request, Page $page, string $strip): JsonResponse
    {
        try {
            $restored = RestoreStrip::run($page, $request->toData($strip), $request->position());
        } catch (StripAlreadyOnPage) {
            $request->reject('strip', 'strip_exists');
        } catch (InvalidStripPosition) {
            $request->reject('position', 'position');
        } catch (UnknownStripType) {
            $request->reject('type', 'type');
        }

        return PageStripsResponse::restored(ListEditableStrips::run($page), $restored);
    }
}
