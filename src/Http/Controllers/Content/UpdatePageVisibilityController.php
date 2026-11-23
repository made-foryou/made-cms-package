<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Controllers\Content;

use Illuminate\Http\RedirectResponse;
use Made\CmsPackage\Content\Actions\SetPageVisibility;
use Made\CmsPackage\Content\Exceptions\HomePageProtected;
use Made\CmsPackage\Content\Models\Page;
use Made\CmsPackage\Http\Requests\Content\UpdatePageVisibilityRequest;
use Made\CmsPackage\Http\Responses\PageRedirect;

/**
 * Hides a page from visitors or shows it again, from the card Weghalen on the screen of the page.
 */
final class UpdatePageVisibilityController
{
    public function __invoke(UpdatePageVisibilityRequest $request, Page $page): RedirectResponse
    {
        try {
            SetPageVisibility::run($page, $request->hidden(), $request->editorId());
        } catch (HomePageProtected) {
            $request->reject('visible', 'home_hide');
        }

        return PageRedirect::visibilityChanged($page);
    }
}
