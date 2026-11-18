<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Controllers\Content;

use Illuminate\Http\JsonResponse;
use Made\CmsPackage\Content\Actions\CompareDraft;
use Made\CmsPackage\Content\Models\Page;
use Made\CmsPackage\Http\Responses\DraftResponse;

/**
 * What the bar of the edit shell shows: how many strips the draft changes and whether it can be published or discarded.
 */
final class ShowDraftController
{
    public function __invoke(Page $page): JsonResponse
    {
        return DraftResponse::state(CompareDraft::run($page), $page);
    }
}
