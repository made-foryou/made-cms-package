<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Controllers\Content;

use Illuminate\Http\JsonResponse;
use Made\CmsPackage\Content\Actions\DescribeStrip;
use Made\CmsPackage\Content\Actions\ListPages;
use Made\CmsPackage\Content\Exceptions\StripNotFound;
use Made\CmsPackage\Content\Exceptions\UnknownStripType;
use Made\CmsPackage\Content\Models\Page;
use Made\CmsPackage\Http\Responses\StripResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * What the side panel builds a strip from: its fields, its draft values and the pages a link can go to.
 */
final class ShowStripController
{
    public function __invoke(Page $page, string $strip): JsonResponse
    {
        try {
            $description = DescribeStrip::run($page, $strip);
        } catch (StripNotFound|UnknownStripType) {
            abort(Response::HTTP_NOT_FOUND);
        }

        return StripResponse::describe($description, ListPages::run());
    }
}
