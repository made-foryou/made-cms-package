<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Controllers\Content;

use Illuminate\Http\RedirectResponse;
use Made\CmsPackage\Content\Actions\CreatePage;
use Made\CmsPackage\Content\Exceptions\InvalidPageSlug;
use Made\CmsPackage\Content\Exceptions\PagePathTaken;
use Made\CmsPackage\Content\Exceptions\PageSlugTooLong;
use Made\CmsPackage\Content\Exceptions\ParentPageDeleted;
use Made\CmsPackage\Content\Exceptions\ParentPageNotFound;
use Made\CmsPackage\Http\Requests\Content\StorePageRequest;
use Made\CmsPackage\Http\Responses\PageRedirect;

/**
 * Creates the page of the wizard and shows that it is ready.
 */
final class StorePageController
{
    public function __invoke(StorePageRequest $request): RedirectResponse
    {
        try {
            $page = CreatePage::run($request->toData());
        } catch (InvalidPageSlug) {
            $request->reject('slug', 'slug_invalid');
        } catch (PageSlugTooLong) {
            $request->reject('slug', 'slug_long');
        } catch (PagePathTaken) {
            $request->reject('slug', 'path_taken');
        } catch (ParentPageNotFound|ParentPageDeleted) {
            $request->reject('parent_id', 'parent_invalid');
        }

        return PageRedirect::created($page);
    }
}
