<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Controllers\Content;

use Illuminate\Http\RedirectResponse;
use Made\CmsPackage\Content\Actions\UpdatePage;
use Made\CmsPackage\Content\Exceptions\CircularPageMove;
use Made\CmsPackage\Content\Exceptions\HomePageProtected;
use Made\CmsPackage\Content\Exceptions\InvalidPageSlug;
use Made\CmsPackage\Content\Exceptions\PagePathTaken;
use Made\CmsPackage\Content\Exceptions\PagePathTooLong;
use Made\CmsPackage\Content\Exceptions\PageSlugTooLong;
use Made\CmsPackage\Content\Exceptions\ParentPageDeleted;
use Made\CmsPackage\Content\Exceptions\ParentPageNotFound;
use Made\CmsPackage\Content\Exceptions\UnknownPageType;
use Made\CmsPackage\Content\Models\Page;
use Made\CmsPackage\Http\Requests\Content\UpdatePageRequest;
use Made\CmsPackage\Http\Responses\PageRedirect;

/**
 * Saves the basics of a page.
 */
final class UpdatePageController
{
    public function __invoke(UpdatePageRequest $request, Page $page): RedirectResponse
    {
        try {
            UpdatePage::run($page, $request->toData($page));
        } catch (InvalidPageSlug) {
            $request->reject('slug', 'slug_invalid');
        } catch (PageSlugTooLong) {
            $request->reject('slug', 'slug_long');
        } catch (PagePathTaken) {
            $request->reject('slug', 'path_taken');
        } catch (PagePathTooLong) {
            $request->reject('slug', 'path_long');
        } catch (HomePageProtected) {
            $request->reject('slug', 'home_protected');
        } catch (CircularPageMove|ParentPageNotFound|ParentPageDeleted) {
            $request->reject('parent_id', 'parent_invalid');
        } catch (UnknownPageType) {
            $request->reject('type', 'type_invalid');
        }

        return PageRedirect::saved($page);
    }
}
