<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Controllers\Content;

use Illuminate\Http\RedirectResponse;
use Made\CmsPackage\Content\Actions\UpdatePageMeta;
use Made\CmsPackage\Content\Models\Page;
use Made\CmsPackage\Http\Requests\Content\UpdateSitemapPageRequest;
use Made\CmsPackage\Http\Responses\SitemapRedirect;

/**
 * The switch "in sitemap" of a page, from the screen of the sitemap: it holds for the site at once, and the sitemap
 * follows after the answer.
 */
final class UpdateSitemapPageController
{
    public function __invoke(UpdateSitemapPageRequest $request, Page $page): RedirectResponse
    {
        UpdatePageMeta::run($page, $request->toData());

        return SitemapRedirect::saved($page, $request->group());
    }
}
