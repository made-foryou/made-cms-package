<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Controllers\Content;

use Illuminate\Http\JsonResponse;
use Made\CmsPackage\Content\Actions\ListEditableStrips;
use Made\CmsPackage\Content\Models\Page;
use Made\CmsPackage\Http\Responses\PageStripsResponse;

/**
 * The strips of the working copy for the opbouw of the edit shell: their summaries and counts follow what the side
 * panel saved, so the opbouw asks for them again when it shows.
 */
final class ShowStripsController
{
    public function __invoke(Page $page): JsonResponse
    {
        return PageStripsResponse::listed(ListEditableStrips::run($page));
    }
}
