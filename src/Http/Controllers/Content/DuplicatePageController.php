<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Controllers\Content;

use Illuminate\Http\RedirectResponse;
use Made\CmsPackage\Content\Actions\DuplicatePage;
use Made\CmsPackage\Content\Exceptions\PagePathTaken;
use Made\CmsPackage\Content\Exceptions\PagePathTooLong;
use Made\CmsPackage\Content\Exceptions\PageSlugTooLong;
use Made\CmsPackage\Content\Exceptions\ParentPageDeleted;
use Made\CmsPackage\Content\Exceptions\ParentPageNotFound;
use Made\CmsPackage\Content\Models\Page;
use Made\CmsPackage\Http\Requests\Content\DuplicatePageRequest;
use Made\CmsPackage\Http\Responses\PageRedirect;

/**
 * Copies a page into a draft and opens the copy.
 */
final class DuplicatePageController
{
    public function __invoke(DuplicatePageRequest $request, Page $page): RedirectResponse
    {
        try {
            $copy = DuplicatePage::run($page, $request->editorId());
        } catch (PagePathTaken) {
            $request->reject('duplicate', 'duplicate_path_taken');
        } catch (PagePathTooLong|PageSlugTooLong) {
            $request->reject('duplicate', 'duplicate_path_long');
        } catch (ParentPageNotFound|ParentPageDeleted) {
            $request->reject('duplicate', 'duplicate_parent');
        }

        return PageRedirect::duplicated($copy, $page);
    }
}
