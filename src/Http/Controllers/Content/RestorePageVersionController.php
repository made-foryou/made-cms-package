<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Controllers\Content;

use Illuminate\Http\RedirectResponse;
use Made\CmsPackage\Content\Actions\RestorePageVersion;
use Made\CmsPackage\Content\Exceptions\PageVersionNotFound;
use Made\CmsPackage\Content\Models\Page;
use Made\CmsPackage\Content\Models\PageVersion;
use Made\CmsPackage\Http\Requests\Content\RestorePageVersionRequest;
use Made\CmsPackage\Http\Responses\PageRedirect;

/**
 * Puts a version of a page back as its draft and goes back to the card of the versions.
 */
final class RestorePageVersionController
{
    public function __invoke(RestorePageVersionRequest $request, Page $page, PageVersion $version): RedirectResponse
    {
        try {
            $restored = RestorePageVersion::run($page, $version, $request->editorId());
        } catch (PageVersionNotFound) {
            abort(404);
        }

        return PageRedirect::versionRestored($page, $restored);
    }
}
