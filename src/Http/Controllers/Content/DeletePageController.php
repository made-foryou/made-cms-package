<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Controllers\Content;

use Illuminate\Http\RedirectResponse;
use Made\CmsPackage\Content\Actions\DeletePage;
use Made\CmsPackage\Content\Exceptions\HomePageProtected;
use Made\CmsPackage\Content\Exceptions\PageHasChildren;
use Made\CmsPackage\Content\Models\Page;
use Made\CmsPackage\Http\Requests\Content\DeletePageRequest;
use Made\CmsPackage\Http\Responses\PageRedirect;

/**
 * Deletes a page and offers to restore it.
 */
final class DeletePageController
{
    public function __invoke(DeletePageRequest $request, Page $page): RedirectResponse
    {
        try {
            DeletePage::run($page);
        } catch (PageHasChildren) {
            $request->reject('delete', 'has_children');
        } catch (HomePageProtected) {
            $request->reject('delete', 'home_delete');
        }

        return PageRedirect::deleted($page);
    }
}
