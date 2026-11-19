<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Controllers\Content;

use Illuminate\Http\RedirectResponse;
use Made\CmsPackage\Content\Actions\UpdatePageMeta;
use Made\CmsPackage\Content\Models\Page;
use Made\CmsPackage\Http\Requests\Content\UpdatePageMetaRequest;
use Made\CmsPackage\Http\Responses\PageRedirect;

/**
 * Saves how Google sees a page, from its card on the screen of the page.
 */
final class UpdatePageMetaController
{
    public function __invoke(UpdatePageMetaRequest $request, Page $page): RedirectResponse
    {
        UpdatePageMeta::run($page, $request->toData());

        return PageRedirect::metaSaved($page);
    }
}
