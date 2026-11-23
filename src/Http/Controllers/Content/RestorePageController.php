<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Controllers\Content;

use Illuminate\Http\RedirectResponse;
use Made\CmsPackage\Content\Actions\RestorePage;
use Made\CmsPackage\Content\Exceptions\PageNotDeleted;
use Made\CmsPackage\Content\Exceptions\PagePathTaken;
use Made\CmsPackage\Content\Exceptions\PagePathTooLong;
use Made\CmsPackage\Content\Exceptions\ParentPageDeleted;
use Made\CmsPackage\Content\Models\Page;
use Made\CmsPackage\Http\Requests\Content\RestorePageRequest;
use Made\CmsPackage\Http\Responses\PageRedirect;

/**
 * Brings a deleted page back.
 */
final class RestorePageController
{
    public function __invoke(RestorePageRequest $request, Page $page): RedirectResponse
    {
        try {
            RestorePage::run($page, $request->editorId());
        } catch (ParentPageDeleted) {
            $request->reject('restore', 'parent_deleted');
        } catch (PagePathTaken) {
            $request->reject('restore', 'restore_path_taken');
        } catch (PagePathTooLong) {
            $request->reject('restore', 'restore_path_long');
        } catch (PageNotDeleted) {
            $request->reject('restore', 'not_deleted');
        }

        return PageRedirect::restored($page);
    }
}
