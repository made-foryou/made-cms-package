<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Controllers\Content;

use Illuminate\Http\RedirectResponse;
use Made\CmsPackage\Content\Actions\UpdatePageMetaInRound;
use Made\CmsPackage\Content\Models\Page;
use Made\CmsPackage\Http\Requests\Content\UpdateMetaTagsRequest;
use Made\CmsPackage\Http\Responses\MetaRedirect;

/**
 * Saves the title and the description of a page from the Meta-overzicht, live at once, and goes on with the round.
 */
final class UpdateMetaTagsController
{
    public function __invoke(UpdateMetaTagsRequest $request, Page $page): RedirectResponse
    {
        return MetaRedirect::saved($page, UpdatePageMetaInRound::run($page, $request->toData()), $request->filter(), $request->done());
    }
}
